<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Rule\CartRuleScope;
use Contena\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleException;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
class PaymentMethodRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingPaymentMethodIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new PaymentMethodRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => [Uuid::randomHex(), Uuid::randomHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id));
    }

    /**
     * @return iterable<array<string|bool|array<string, string|array<string>>>>
     */
    public static function matchDataProvider(): iterable
    {
        yield 'equals operator rejects when no payment methods are configured' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'paymentMethodIds' => [],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'equals operator rejects a different payment method' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'paymentMethodIds' => ['ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'not equals operator rejects the configured payment method' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'not equals operator rejects one of multiple configured payment methods' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ff5a0713093841ceb86b0f83edd7dab4',
            false,
        ];
        yield 'equals operator matches the configured payment method' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
            ],
            '965a0713093841ceb86b0f83edd7dab4',
            true,
        ];
        yield 'equals operator matches one of multiple configured payment methods' => [
            [
                'operator' => Rule::OPERATOR_EQ,
                'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ff5a0713093841ceb86b0f83edd7dab4',
            true,
        ];
        yield 'not equals operator matches an unconfigured payment method' => [
            [
                'operator' => Rule::OPERATOR_NEQ,
                'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
            ],
            'ee5a0713093841ceb86b0f83edd7dab4',
            true,
        ];
    }

    /**
     * @param array<string, string|array<string>> $ruleProperties
     */
    #[DataProvider('matchDataProvider')]
    public function testMatch(array $ruleProperties, string $paymentMethodId, bool $expected): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign($ruleProperties);

        $paymentMethodEntity = new PaymentMethodEntity();
        $paymentMethodEntity->setId($paymentMethodId);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $channelContext
        );

        static::assertSame($expected, $paymentMethodRule->match($ruleScope));
    }

    public function testExpectUnsupportedOperatorException(): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign(['operator' => 'FOO', 'paymentMethodIds' => []]);

        $paymentMethodEntity = new PaymentMethodEntity();
        $paymentMethodEntity->setId('965a0713093841ceb86b0f83edd7dab4');

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $channelContext
        );

        $this->expectExceptionObject(RuleException::unsupportedOperator('FOO', RuleComparison::class));

        $paymentMethodRule->match($ruleScope);
    }
}
