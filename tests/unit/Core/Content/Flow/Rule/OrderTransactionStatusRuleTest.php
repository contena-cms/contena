<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Rule\FlowRuleScope;
use Contena\Core\Content\Flow\Rule\OrderTransactionStatusRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @internal
 */
#[CoversClass(OrderTransactionStatusRule::class)]
#[Group('rules')]
class OrderTransactionStatusRuleTest extends TestCase
{
    private OrderTransactionStatusRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderTransactionStatusRule();
    }

    public function testName(): void
    {
        static::assertSame('orderTransactionStatus', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('stateIds', $constraints, 'stateIds constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['stateIds']);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    /**
     * @param list<string> $selectedOrderStateIds
     */
    #[DataProvider('getMatchingValues')]
    public function testOrderPaymentStatusRuleMatching(bool $expected, string $orderStateId, array $selectedOrderStateIds, string $operator): void
    {
        $orderTransactionId = Uuid::randomHex();

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName(OrderTransactionStates::STATE_IN_PROGRESS);
        $orderTransactionCollection = new OrderTransactionCollection();
        $orderTransaction = new OrderTransactionEntity();
        $orderTransaction->setId($orderTransactionId);
        $orderTransaction->setStateId($orderStateId);
        $orderTransaction->setStateMachineState($stateMachineState);
        $orderTransactionCollection->add($orderTransaction);
        $order = new OrderEntity();
        $order->setTransactions($orderTransactionCollection);
        $order->setPrimaryOrderTransactionId($orderTransactionId);
        $order->setPrimaryOrderTransaction($orderTransaction);
        $scope = new FlowRuleScope(
            $order,
            new Cart('test'),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['stateIds' => $selectedOrderStateIds, 'operator' => $operator]);
        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testConfig(): void
    {
        $config = (new OrderTransactionStatusRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string, list<string>, string}>
     */
    public static function getMatchingValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_NEQ],
        ];
    }
}
