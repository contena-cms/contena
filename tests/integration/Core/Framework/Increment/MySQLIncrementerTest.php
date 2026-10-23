<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Increment\MySQLIncrementer;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class MySQLIncrementerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MySQLIncrementer $mysqlIncrementer;

    protected function setUp(): void
    {
        $this->mysqlIncrementer = new MySQLIncrementer(static::getContainer()->get(Connection::class), new NativeClock());
        $this->mysqlIncrementer->setPool('admin_user-activity-pool');
    }

    public function testIncrement(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertNotNull($list['sw.blog.index']);
        static::assertSame(1, $list['sw.blog.index']['count']);

        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(2, $list['sw.blog.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertNotNull($list['sw.blog.index']);
        static::assertSame(2, $list['sw.blog.index']['count']);

        $this->mysqlIncrementer->decrement('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
    }

    public function testList(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(2, array_values($list)[0]['count']);
        static::assertSame('sw.blog.index', array_values($list)[0]['key']);
        static::assertSame(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(3, array_values($list)[0]['count']);
        static::assertSame('sw.order.index', array_values($list)[0]['key']);
        static::assertSame(2, array_values($list)[1]['count']);
    }

    public function testReset(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->mysqlIncrementer->reset('test-admin_user-1');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(0, $list['sw.blog.index']['count']);

        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.order.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
        static::assertSame(1, $list['sw.order.index']['count']);

        $this->mysqlIncrementer->reset('test-admin_user-1', 'sw.order.index');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertSame(1, $list['sw.blog.index']['count']);
        static::assertSame(0, $list['sw.order.index']['count']);
    }

    public function testDeleteKeys(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.create');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->mysqlIncrementer->delete('test-admin_user-1', ['sw.blog.index']);

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertEquals([
            'sw.blog.create' => [
                'pool' => 'admin_user-activity-pool',
                'cluster' => 'test-admin_user-1',
                'key' => 'sw.blog.create',
                'count' => 1,
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.index');
        $this->mysqlIncrementer->increment('test-admin_user-1', 'sw.blog.create');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertNotCount(0, $list);

        $this->mysqlIncrementer->delete('test-admin_user-1');

        $list = $this->mysqlIncrementer->list('test-admin_user-1');

        static::assertCount(0, $list);
    }
}
