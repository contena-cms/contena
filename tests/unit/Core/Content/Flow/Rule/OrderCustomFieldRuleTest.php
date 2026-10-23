<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Rule\FlowRuleScope;
use Contena\Core\Content\Flow\Rule\OrderCustomFieldRule;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(OrderCustomFieldRule::class)]
#[Group('rules')]
class OrderCustomFieldRuleTest extends TestCase
{
    private const CUSTOM_FIELD_NAME = 'custom_test';



    protected function setUp(): void
    {
        $this->rule = new OrderCustomFieldRule();

        $this->order = new OrderEntity();
    }

    public function testGetName(): void
    {
        static::assertSame('orderCustomField', $this->rule->getName());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = static::createStub(TestRuleScope::class);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testGetConstraintsWithRenderedField(): void
    {
        $this->rule->assign([
            'renderedField' => [
                'type' => 'string',
            ],
        ]);

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
    }

    public function testMatch(): void
    {
        $this->order->assign(['customFields' => [
            self::CUSTOM_FIELD_NAME => 'my_invalid_value',
        ]]);

        $scope = new FlowRuleScope($this->order, new Cart('test'), static::createStub(ChannelContext::class));

        $this->rule->assign(
            [
                'operator' => '=',
                'renderedField' => [
                    'type' => 'string',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => 'my_test_value',
            ]
        );

        static::assertFalse($this->rule->match($scope));
    }
}
