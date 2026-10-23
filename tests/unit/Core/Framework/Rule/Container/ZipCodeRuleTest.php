<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\ZipCodeRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleException;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[CoversClass(ZipCodeRule::class)]
class ZipCodeRuleTest extends TestCase
{
    public function testMatchZipCodeThrowsExceptionWithUnsupportedOperator(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedOperator(
                'bad-operator',
                ZipCodeRule::class
            )
        );

        $rule = new class('bad-operator', ['12345']) extends ZipCodeRule {
            final public const RULE_NAME = 'zipCodeTest';

            public function match(RuleScope $scope): bool
            {
                return false;
            }

            public function matchAddress(UserAddressEntity $address): bool
            {
                return $this->matchZipCode($address);
            }
        };

        $rule->matchAddress(new UserAddressEntity());
    }

    public function testMatchZipCodeThrowsExceptionWithUnsupportedValue(): void
    {
        $this->expectExceptionObject(
            RuleException::unsupportedValue(
                'NULL',
                ZipCodeRule::class
            )
        );

        $rule = new class(Rule::OPERATOR_EQ) extends ZipCodeRule {
            final public const RULE_NAME = 'zipCodeTest';

            public function match(RuleScope $scope): bool
            {
                return false;
            }

            public function matchAddress(UserAddressEntity $address): bool
            {
                return $this->matchZipCode($address);
            }
        };

        $rule->matchAddress(new UserAddressEntity());
    }
}
