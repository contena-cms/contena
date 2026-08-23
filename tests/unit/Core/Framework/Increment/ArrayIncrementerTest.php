<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Increment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Increment\ArrayIncrementer;

/**
 * @internal
 */
#[CoversClass(ArrayIncrementer::class)]
class ArrayIncrementerTest extends TestCase
{
    private ArrayIncrementer $arrayIncrementer;

    protected function setUp(): void
    {
        $this->arrayIncrementer = new ArrayIncrementer();
        $this->arrayIncrementer->setPool('user-activity-pool');
    }

    public function testDecrementDoesNotCreate(): void
    {
        $this->arrayIncrementer->decrement('test', 'test');
        static::assertEmpty($this->arrayIncrementer->list('test'));
    }

    public function testIncrement(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotNull($list['ct.product.index']);
        static::assertSame(1, $list['ct.product.index']['count']);

        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(2, $list['ct.product.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotNull($list['ct.product.index']);
        static::assertSame(2, $list['ct.product.index']['count']);

        $this->arrayIncrementer->decrement('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['ct.product.index']['count']);
    }

    public function testList(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(2, array_values($list)[0]['count']);
        static::assertSame('ct.product.index', array_values($list)[0]['key']);
        static::assertSame(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->arrayIncrementer->increment('test-user-1', 'ct.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.order.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(3, array_values($list)[0]['count']);
        static::assertSame('ct.order.index', array_values($list)[0]['key']);
        static::assertSame(2, array_values($list)[1]['count']);

        static::assertEmpty($this->arrayIncrementer->list('test2'));
    }

    public function testReset(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->reset('test-user-1');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(0, $list['ct.product.index']['count']);

        $this->arrayIncrementer->increment('test-user-1', 'ct.order.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['ct.product.index']['count']);
        static::assertSame(1, $list['ct.order.index']['count']);

        $this->arrayIncrementer->reset('test-user-1', 'ct.order.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame(1, $list['ct.product.index']['count']);
        static::assertSame(0, $list['ct.order.index']['count']);
    }

    public function testDeleteClusterWithKeys(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.create');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.update');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->delete('test-user-1', ['ct.product.index', 'ct.product.create']);

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertSame([
            'ct.product.update' => [
                'key' => 'ct.product.update',
                'cluster' => 'test-user-1',
                'pool' => 'user-activity-pool',
                'count' => 1,
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');
        $this->arrayIncrementer->increment('test-user-1', 'ct.product.index');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->arrayIncrementer->delete('test-user-1');

        $list = $this->arrayIncrementer->list('test-user-1');

        static::assertEmpty($list);
    }
}
