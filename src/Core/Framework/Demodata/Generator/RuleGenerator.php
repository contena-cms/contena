<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Faker\Generator;
use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Contena\Core\System\User\Rule\UserGroupRule;
use Contena\Core\System\User\Rule\DaysSinceFirstLoginRule;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\Container;
use Contena\Core\Framework\Rule\Container\FilterRule;
use Contena\Core\Framework\Rule\Container\OrRule;
use Contena\Core\Framework\Rule\DateRangeRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Currency\Rule\CurrencyRule;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class RuleGenerator implements DemodataGeneratorInterface
{
    private Generator $faker;

    /**
     * @internal
     *
     * @param EntityRepository<RuleCollection> $ruleRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     */
    public function __construct(
        private readonly EntityRepository $ruleRepository,
        private readonly EntityWriterInterface $writer,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly RuleDefinition $ruleDefinition,
        private readonly ClockInterface $clock
    ) {
    }

    public function getDefinition(): string
    {
        return RuleDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();

        $paymentMethodIds = $this->paymentMethodRepository->searchIds(new Criteria(), $context->getContext())->getIds();
        $shippingMethodIds = $this->shippingMethodRepository->searchIds(new Criteria(), $context->getContext())->getIds();

        $criteria = (new Criteria())->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsAnyFilter('rule.shippingMethods.id', $shippingMethodIds),
                    new EqualsAnyFilter('rule.paymentMethods.id', $paymentMethodIds),
                ]
            )
        );

        $ids = $this->ruleRepository->searchIds($criteria, $context->getContext());

        if ($ids->getIds() !== []) {
            return;
        }

        $pool = [
            [
                'rule' => (new DaysSinceFirstLoginRule())->assign(['daysPassed' => 0]),
                'name' => 'New user',
            ],
            [
                'rule' => (new DateRangeRule())->assign(['fromDate' => $this->clock->now(), 'toDate' => $this->clock->now()->modify('+2 day')]),
                'name' => 'Next two days',
            ],
            [
                'rule' => (new GoodsPriceRule())->assign(['amount' => 5000, 'operator' => GoodsPriceRule::OPERATOR_GTE]),
                'name' => 'Cart >= 5000',
            ],
            [
                'rule' => (new UserGroupRule())->assign(['userGroupIds' => [TestDefaults::FALLBACK_CUSTOMER_GROUP]]),
                'name' => 'Default group',
            ],
            [
                'rule' => (new CurrencyRule())->assign(['currencyIds' => [Defaults::CURRENCY]]),
                'name' => 'Default currency',
            ],
        ];

        $payload = [];
        for ($i = 0; $i < 20; ++$i) {
            $rules = \array_slice($pool, random_int(0, \count($pool) - 2), random_int(1, 2));

            $classes = array_column($rules, 'rule');
            $names = array_column($rules, 'name');

            $ruleData = [
                'id' => Uuid::randomHex(),
                'priority' => $i,
                'name' => implode(' + ', $names),
                'description' => $context->getFaker()->text(),
                'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
            ];

            $ruleData['conditions'][] = $this->buildChildRule(null, (new OrRule())->assign(['rules' => $classes]));

            $payload[] = $ruleData;
        }

        // nested condition
        $nestedRule = new OrRule();

        $nestedRuleData = [
            'id' => Uuid::randomHex(),
            'priority' => 20,
            'name' => 'nested rule',
            'description' => $context->getFaker()->text(),
        ];

        $this->buildNestedRule($nestedRule, $pool, 0, 6);

        $nestedRuleData['conditions'][] = $this->buildChildRule(null, $nestedRule);

        $payload[] = $nestedRuleData;

        $writeContext = WriteContext::createFromContext($context->getContext());

        $this->writer->insert($this->ruleDefinition, $payload, $writeContext);
    }

    /**
     * @param list<array{rule: Rule, name: string}> $pool
     */
    private function buildNestedRule(Rule $rule, array $pool, int $currentDepth, int $depth): Rule
    {
        if ($currentDepth === $depth) {
            return $rule;
        }

        $rules = $this->faker->randomElements($pool, 2);

        $classes = array_column($rules, 'rule');

        if ($currentDepth % 2 === 1) {
            $classes[] = $this->buildNestedRule(new OrRule(), $pool, $currentDepth + 1, $depth);
        } else {
            $classes[] = $this->buildNestedRule(new AndRule(), $pool, $currentDepth + 1, $depth);
        }

        $rule->assign(['rules' => $classes]);

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChildRule(?string $parentId, Rule $rule): array
    {
        $data = [];
        $data['value'] = $rule->jsonSerialize();
        unset($data['value']['_class'], $data['value']['rules'], $data['value']['extensions']);

        if ($rule instanceof FilterRule) {
            unset($data['value']['filter']);
        }

        if (!$data['value']) {
            unset($data['value']);
        }
        $data['id'] = Uuid::randomHex();
        $data['parentId'] = $parentId;
        $data['type'] = $rule->getName();

        if ($rule instanceof Container) {
            $data['children'] = [];
            foreach ($rule->getRules() as $index => $childRule) {
                $child = $this->buildChildRule($data['id'], $childRule);
                $child['position'] = $index;
                $data['children'][] = $child;
            }
        }

        return $data;
    }
}
