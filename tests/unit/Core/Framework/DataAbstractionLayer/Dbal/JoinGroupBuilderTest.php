<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\JoinGroup;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaPartInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(JoinGroupBuilder::class)]
class JoinGroupBuilderTest extends TestCase
{
    public function testCanGroupProvidedFilters(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                BlogCategoryDefinition::class,
                CategoryDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(BlogDefinition::class);

        $filters = [
            new EqualsFilter('active', true),
        ];

        $builder = new JoinGroupBuilder();
        $groupedFilters = $builder->group($filters, $definition, ['blog.categories']);

        static::assertCount(3, $groupedFilters);
        static::assertInstanceOf(EqualsFilter::class, $groupedFilters[0]);
        static::assertInstanceOf(EqualsFilter::class, $groupedFilters[1]);
        static::assertInstanceOf(JoinGroup::class, $groupedFilters[2]);
    }

    /**
     * @param list<Filter> $filters
     * @param list<CriteriaPartInterface> $expected
     */
    #[DataProvider('nestedGroupingProvider')]
    public function testNestedGrouping(array $filters, array $expected): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [
                StateMachineDefinition::class,
                StateMachineStateDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $builder = new JoinGroupBuilder();

        $groups = $builder->group($filters, $registry->get(OrderDefinition::class));

        static::assertEquals($expected, $groups);
    }

    public static function nestedGroupingProvider(): \Generator
    {
        yield 'Call empty' => [
            [],
            [],
        ];

        yield 'Single filter, no grouping' => [
            [new EqualsFilter('transactions.paymentMethodId', 'paypal')],
            [new EqualsFilter('transactions.paymentMethodId', 'paypal')],
        ];

        yield 'Multiple filters, no grouping' => [
            [
                new EqualsFilter('currencyFactor', 1),
                new EqualsFilter('source', 'foo'),
                new EqualsFilter('shippingTotal', 2.0),
            ],
            [
                new EqualsFilter('currencyFactor', 1),
                new EqualsFilter('source', 'foo'),
                new EqualsFilter('shippingTotal', 2.0),
            ],
        ];

        yield 'Multiple filters, single many filter, no grouping' => [
            [
                new EqualsFilter('currencyFactor', 1),
            ],
            [
                new EqualsFilter('currencyFactor', 1),
                new EqualsFilter('transactions.paymentMethodId', 'paypal'),
                new EqualsFilter('transactions.amount', 1.0),
            ],
        ];

        yield 'Multiple filters, multiple many filter, no grouping' => [
            [
                new EqualsFilter('currencyFactor', 1),
            ],
            [
                new EqualsFilter('currencyFactor', 1),
            ],
        ];

        yield 'Reported issue scenario' => [
            [
                new OrFilter([
                    new AndFilter([
                        new EqualsFilter('transactions.paymentMethodId', 'paypal'),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                    ]),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                ]),
            ],
            [
            ],
        ];

        yield 'Multiple many filters, but different path' => [
            [
                new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', 'paypal'),
                    new EqualsFilter('transactions.amount', 2.0),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'foo'),
                ]),
                new AndFilter([
                    new EqualsFilter('lineItems.type', 'blog'),
                    new EqualsFilter('lineItems.label', 'foo'),
                ]),
            ],
            [
                new EqualsFilter('transactions.paymentMethodId', 'paypal'),
                new EqualsFilter('transactions.amount', 2.0),
                new EqualsFilter('transactions.stateMachineState.technicalName', 'foo'),
                new EqualsFilter('lineItems.type', 'blog'),
                new EqualsFilter('lineItems.label', 'foo'),
            ],
        ];
    }
}
