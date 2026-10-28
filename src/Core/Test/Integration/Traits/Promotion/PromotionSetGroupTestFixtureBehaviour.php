<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits\Promotion;

use Contena\Core\Checkout\Cart\Rule\LineItemRule;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('checkout')]
trait PromotionSetGroupTestFixtureBehaviour
{

    private function createSetGroupWithRuleFixture(string $groupId, string $packagerKey, float $value, string $sorterKey, string $promotionId, string $ruleId, ContainerInterface $container): string
    {
        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $repository = $container->get('promotion_setgroup.repository');

        $data = [
            'id' => $groupId,
            'promotionId' => $promotionId,
            'packagerKey' => $packagerKey,
            'sorterKey' => $sorterKey,
            'value' => $value,
        ];

        $repository->create([$data], $context->getContext());

        $ruleRepository = $container->get('promotion_setgroup_rule.repository');

        $dataAssoc = [
            'setgroupId' => $groupId,
            'ruleId' => $ruleId,
        ];

        $ruleRepository->create([$dataAssoc], $context->getContext());

        return $groupId;
    }

    /**
     * @param array<string> $lineItemIds
     */
    private function createRule(string $name, array $lineItemIds, ContainerInterface $container): string
    {
        $context = $container->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $ruleRepository = $container->get('rule.repository');
        $conditionRepository = $container->get('rule_condition.repository');

        $ruleId = Uuid::randomHex();
        $ruleRepository->create(
            [['id' => $ruleId, 'name' => $name, 'priority' => 1]],
            $context->getContext()
        );

        $id = Uuid::randomHex();
        $conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'identifiers' => $lineItemIds,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $context->getContext());

        return $ruleId;
    }
}
