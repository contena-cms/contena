<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Test\Stub\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(IdsCollection::class)]
class IdsCollectionTest extends TestCase
{
    public function testIdsCollection(): void
    {
        $ids = new IdsCollection();
        $id = $ids->create('test');

        $ids->set('foo', $id);

        static::assertSame($id, $ids->get('foo'));
        static::assertSame($id, $ids->get('test'));
        static::assertSame([$id], array_values($ids->getList(['test'])));
        static::assertSame([['id' => $id]], $ids->getIdArray(['test']));
        static::assertSame(Uuid::fromHexToBytes($id), $ids->getBytes('test'));
        static::assertSame([['id' => $id]], $ids->getIdArray(['test']));
    }
}
