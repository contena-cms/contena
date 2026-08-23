<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Test\Stub\Doctrine\FakeResultFactory;

/**
 * @internal
 */
#[CoversClass(FakeResultFactory::class)]
class FakeResultFactoryTest extends TestCase
{
    public function testCreateResult(): void
    {
        $data = [
            ['id' => 1, 'name' => 'foo', 'description' => 'bar description'],
            ['id' => 2, 'name' => 'bar', 'description' => 'foo description'],
        ];

        $connection = static::createStub(Connection::class);

        $result = FakeResultFactory::createResult($data, $connection);

        static::assertSame(2, $result->rowCount());
        static::assertSame(3, $result->columnCount());
        static::assertSame($data, $result->fetchAllAssociative());
    }
}
