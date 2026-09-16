<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(IdSearchResult::class)]
class IdSearchResultTest extends TestCase
{
    public function testEmptyResultHasNoPrimaryKeyData(): void
    {
        $result = IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext());

        static::assertSame([], $result->getPrimaryKeyData());
    }

    public function testScalarIdsBecomeIdPayloads(): void
    {
        $result = IdSearchResult::fromIds(['first-id', 'second-id'], new Criteria(), Context::createDefaultContext());

        static::assertSame(
            [['id' => 'first-id'], ['id' => 'second-id']],
            $result->getPrimaryKeyData()
        );
    }

    public function testCompositePrimaryKeysStayUnchanged(): void
    {
        $primaryKeys = [
            ['blogId' => 'blog-1', 'categoryId' => 'category-1'],
            ['blogId' => 'blog-2', 'categoryId' => 'category-2'],
        ];
        $result = new IdSearchResult(
            2,
            [
                'blog-1-category-1' => ['primaryKey' => $primaryKeys[0], 'data' => []],
                'blog-2-category-2' => ['primaryKey' => $primaryKeys[1], 'data' => []],
            ],
            new Criteria(),
            Context::createDefaultContext()
        );

        static::assertSame($primaryKeys, $result->getPrimaryKeyData());
    }
}
