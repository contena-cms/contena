<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Rule\OrderCreatedByAdminRule;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(OrderCreatedByAdminRule::class)]
#[Group('rules')]
class OrderCreatedByAdminRuleTest extends TestCase
{

    protected function setUp(): void
    {
        $this->rule = new OrderCreatedByAdminRule();
    }

    public function testGetName(): void
    {
        static::assertSame('orderCreatedByAdmin', $this->rule->getName());
    }

    public function testRuleConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertEquals([
            'fields' => [
                'shouldOrderBeCreatedByAdmin' => [
                    'name' => 'shouldOrderBeCreatedByAdmin',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testGetConstraints(): void
    {
        $rule = new OrderCreatedByAdminRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('shouldOrderBeCreatedByAdmin', $constraints, 'Constraint shouldOrderBeCreatedByAdmin not found in Rule');
        static::assertEquals($constraints['shouldOrderBeCreatedByAdmin'], [
            new NotNull(),
            new Type(type: 'bool'),
        ]);
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = static::createStub(TestRuleScope::class);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }


    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not created by admin => Not match because order created by admin' => [
            (new OrderEntity())->assign(['createdById' => Uuid::randomHex()]),
            false,
        ];

        yield 'Condition is created by admin => Not match because order is registered' => [
            new OrderEntity(),
            false,
        ];

        yield 'Condition is not created by admin => Match because order registered' => [
            new OrderEntity(),
            true,
        ];

        yield 'Condition is created by admin => Match because order created by admin' => [
            (new OrderEntity())->assign(['createdById' => Uuid::randomHex()]),
            true,
        ];
    }
}
