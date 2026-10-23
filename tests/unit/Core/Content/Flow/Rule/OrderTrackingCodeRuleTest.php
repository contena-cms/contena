<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Rule\FlowRuleScope;
use Contena\Core\Content\Flow\Rule\OrderTrackingCodeRule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(OrderTrackingCodeRule::class)]
#[Group('rules')]
class OrderTrackingCodeRuleTest extends TestCase
{

    protected function setUp(): void
    {
        $this->rule = new OrderTrackingCodeRule();
    }


    /**
     * @return iterable<string, array{OrderTrackingCodeRule, list<string>, bool}>
     */
    public static function getRuleTestData(): iterable
    {
        yield 'Test if the rule matches with one tracking code and isSet is true' => [
            new OrderTrackingCodeRule(true),
            ['TrackingCode123'],
            true,
        ];

        yield 'Test if the rule matches with multiple tracking codes and isSet is true' => [
            new OrderTrackingCodeRule(true),
            ['TrackingCode123', 'TrackingCode456', 'TrackingCode789'],
            true,
        ];

        yield 'Test if the rule dont matches with no tracking code set and isSet is true' => [
            new OrderTrackingCodeRule(true),
            [],
            false,
        ];

        yield 'Test if the rule matches with no tracking code set and isSet is false' => [
            new OrderTrackingCodeRule(false),
            [],
            true,
        ];

        yield 'Test if the rule dont matches with an empty tracking code string and isSet is true' => [
            new OrderTrackingCodeRule(true),
            [''],
            false,
        ];

        yield 'Test if the rule matches with empty tracking code string and isSet to false' => [
            new OrderTrackingCodeRule(false),
            [''],
            true,
        ];
    }

    public function testNoOrderDeliveries(): void
    {
        $scope = new FlowRuleScope(
            new OrderEntity(),
            new Cart('test'),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['isSet' => true]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testNotExpectedRuleScope(): void
    {
        $ruleScope = static::createStub(RuleScope::class);

        $this->rule->assign(['isSet' => true]);
        static::assertFalse($this->rule->match($ruleScope));
    }

    public function testConstraint(): void
    {
        $constraints = (new OrderTrackingCodeRule())->getConstraints();

        static::assertArrayHasKey('isSet', $constraints);
        static::assertEquals([
            'isSet' => [new NotNull(), new Type(type: 'bool')],
        ], $constraints);
    }

    public function testGetConfig(): void
    {
        $rule = new OrderTrackingCodeRule(true);
        $config = $rule->getConfig();

        static::assertEquals([
            'operatorSet' => null,
            'fields' => [
                'isSet' => [
                    'name' => 'isSet',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
        ], $config->getData());
    }
}
