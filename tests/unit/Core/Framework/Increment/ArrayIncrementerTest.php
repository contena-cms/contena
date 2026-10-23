<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Increment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Increment\ArrayIncrementer;
use Contena\Core\Framework\Log\Package;

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
        $this->arrayIncrementer->setPool('admin_user-activity-pool');
    }

    public function testDecrementDoesNotCreate(): void
    {
        $this->arrayIncrementer->decrement('test', 'test');
        static::assertCount(0, $this->arrayIncrementer->list('test'));
    }

    public function testIncrement(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertNotNull($list['sw.blog.index']);
        static::assertSame(1, $list['sw.blog.index']['count']);

        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(2, $list['sw.blog.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertNotNull($list['sw.blog.index']);
        static::assertSame(2, $list['sw.blog.index']['count']);

        $this->arrayIncrementer->decrement('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
    }

    public function testList(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(2, array_values($list)[0]['count']);
        static::assertSame('sw.blog.index', array_values($list)[0]['key']);
        static::assertSame(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.order.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(3, array_values($list)[0]['count']);
        static::assertSame('sw.order.index', array_values($list)[0]['key']);
        static::assertSame(2, array_values($list)[1]['count']);

        static::assertCount(0, $this->arrayIncrementer->list('test2'));
    }

    public function testReset(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->arrayIncrementer->reset('test-admin_user-1');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(0, $list['sw.blog.index']['count']);

        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
        static::assertSame(1, $list['sw.order.index']['count']);

        $this->arrayIncrementer->reset('test-admin_user-1', 'sw.order.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
        static::assertSame(0, $list['sw.order.index']['count']);
    }

    public function testDeleteClusterWithKeys(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.create');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.update');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->arrayIncrementer->delete('test-admin_user-1', ['sw.blog.index', 'sw.blog.create']);

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertSame([
            'sw.blog.update' => [
                'key' => 'sw.blog.update',
                'cluster' => 'test-admin_user-1',
                'pool' => 'admin_user-activity-pool',
                'count' => 1,
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->arrayIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->arrayIncrementer->delete('test-admin_user-1');

        $list = $this->arrayIncrementer->list('test-admin_user-1');

        static::assertCount(0, $list);
    }
}
