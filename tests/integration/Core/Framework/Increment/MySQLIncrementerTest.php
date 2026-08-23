<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Increment\MySQLIncrementer;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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
        $this->mysqlIncrementer->setPool('user-activity-pool');
    }

    public function testIncrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['contena.media.index']);
        static::assertSame(1, $list['contena.media.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(2, $list['contena.media.index']['count']);
    }

    public function testDecrement(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotNull($list['contena.media.index']);
        static::assertSame(2, $list['contena.media.index']['count']);

        $this->mysqlIncrementer->decrement('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(1, $list['contena.media.index']['count']);
    }

    public function testList(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.user.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(2, array_values($list)[0]['count']);
        static::assertSame('contena.media.index', array_values($list)[0]['key']);
        static::assertSame(1, array_values($list)[1]['count']);

        // List will return in DESC order of record's count
        $this->mysqlIncrementer->increment('test-user-1', 'contena.user.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.user.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(3, array_values($list)[0]['count']);
        static::assertSame('contena.user.index', array_values($list)[0]['key']);
        static::assertSame(2, array_values($list)[1]['count']);
    }

    public function testReset(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->reset('test-user-1');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(0, $list['contena.media.index']['count']);

        $this->mysqlIncrementer->increment('test-user-1', 'contena.user.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(1, $list['contena.media.index']['count']);
        static::assertSame(1, $list['contena.user.index']['count']);

        $this->mysqlIncrementer->reset('test-user-1', 'contena.user.index');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertSame(1, $list['contena.media.index']['count']);
        static::assertSame(0, $list['contena.user.index']['count']);
    }

    public function testDeleteKeys(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.create');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->delete('test-user-1', ['contena.media.index']);

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEquals([
            'contena.media.create' => [
                'pool' => 'user-activity-pool',
                'cluster' => 'test-user-1',
                'key' => 'contena.media.create',
                'count' => 1,
            ],
        ], $list);
    }

    public function testDeleteCluster(): void
    {
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.index');
        $this->mysqlIncrementer->increment('test-user-1', 'contena.media.create');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertNotEmpty($list);

        $this->mysqlIncrementer->delete('test-user-1');

        $list = $this->mysqlIncrementer->list('test-user-1');

        static::assertEmpty($list);
    }
}
