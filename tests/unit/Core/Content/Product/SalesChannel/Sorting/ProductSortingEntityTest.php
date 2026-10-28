<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\Sorting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductSortingEntity::class)]
class ProductSortingEntityTest extends TestCase
{
    public static function dalSortingProvider(): \Generator
    {
        yield 'Test empty config' => [
            [],
            [
            ],
        ];

        yield 'Test one field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Test multiple fields' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'price', 'order' => 'asc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Test skip default id field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'id', 'order' => 'desc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Also skip when product.id is inside' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'product.id', 'order' => 'desc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Sort by priority' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
                ['field' => 'product.id', 'order' => 'desc', 'priority' => 3],
                ['field' => 'sales', 'order' => 'asc', 'priority' => 2],
            ],
            [
            ],
        ];
    }

    /**
     * @param array<mixed> $fields
     * @param array<FieldSorting> $expected
     */
    #[DataProvider('dalSortingProvider')]
    public function testCreateDalSorting(array $fields, $expected): void
    {
        $entity = new ProductSortingEntity();
        $entity->setFields($fields);

        static::assertEquals(
            $expected,
            $entity->createDalSorting()
        );
    }

    public static function dalSortingDefaultSortBScoreProvider(): \Generator
    {
        yield 'Test empty config' => [
            [],
            [
            ],
        ];

        yield 'Test one field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Test without fallback sorting' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
            ],
            [
            ],
            null,
        ];

        yield 'Test multiple fields' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => 'price', 'order' => 'asc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Test skip default _score field' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 2],
                ['field' => '_score', 'order' => 'asc', 'priority' => 1],
            ],
            [
            ],
        ];

        yield 'Sort by priority' => [
            [
                ['field' => 'name', 'order' => 'desc', 'priority' => 1],
                ['field' => '_score', 'order' => 'asc', 'priority' => 3],
                ['field' => 'sales', 'order' => 'asc', 'priority' => 2],
            ],
            [
            ],
        ];
    }

    /**
     * @param array<mixed> $fields
     * @param array<FieldSorting> $expected
     */
    #[DataProvider('dalSortingDefaultSortBScoreProvider')]
    public function testCreateDalSortingWithFallbackSorting(array $fields, array $expected, ?FieldSorting $fallbackSorting = null): void
    {
        $entity = new ProductSortingEntity();
        $entity->setFields($fields);

        static::assertEquals(
            $expected,
            $entity->createDalSorting($fallbackSorting)
        );
    }
}
