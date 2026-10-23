<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\NotRule;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\RuleException;
use Contena\Core\Framework\Rule\SimpleRule;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(NotRule::class)]
class NotRuleTest extends TestCase
{
    public function testUnsupportedValue(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(RuleException::class);
        }
        $rule = new NotRule();
        $channelContext = static::createStub(ChannelContext::class);
        $rule->match(new CheckoutRuleScope($channelContext));
    }

    public function testAddRuleOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->addRule(new SimpleRule());
        $rule->addRule(new SimpleRule());
    }

    public function testSetRulesOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->setRules([new SimpleRule(), new SimpleRule()]);
    }
}
