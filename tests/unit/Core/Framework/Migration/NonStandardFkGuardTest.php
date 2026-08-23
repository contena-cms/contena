<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\NonStandardFkGuard;

/**
 * @internal
 */
#[CoversClass(NonStandardFkGuard::class)]
class NonStandardFkGuardTest extends TestCase
{
    private const string DDL = 'ALTER TABLE `media` ADD COLUMN `foo` VARCHAR(32) NULL';

    private const int ER_DROP_INDEX_FK = 1553;

    #[TestDox('a succeeding statement runs unmodified without touching the guard')]
    public function testRunsStatementUnmodifiedWhenItSucceeds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with(self::DDL);
        $connection->expects($this->never())->method('fetchAssociative');

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    #[TestDox('failures other than error 1553 are rethrown without a retry')]
    public function testRethrowsOtherFailuresWithoutRetry(): void
    {
        $failure = new FakeDriverException(1091, 'Cannot drop index');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willThrowException($failure);
        $connection->expects($this->never())->method('fetchAssociative');

        $this->expectExceptionObject($failure);

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    /**
     * @param array<string, string>|false $guardState
     */
    #[DataProvider('unexplainableGuardStates')]
    public function testRethrowsWhenGuardCannotExplainFailure(array|false $guardState): void
    {
        $failure = new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index needed by foreign key');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willThrowException($failure);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($guardState);

        $this->expectExceptionObject($failure);

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    /**
     * @return \Generator<string, array{array<string, string>|false}>
     */
    public static function unexplainableGuardStates(): \Generator
    {
        yield 'variable absent' => [false];
        yield 'guard already relaxed' => [['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'OFF']];
    }

    public function testRetriesWithRelaxedGuardAndRestoresIt(): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(4))->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$statements): int {
                $statements[] = $sql;
                if (\count($statements) === 1) {
                    throw new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index needed by foreign key');
                }

                return 0;
            });
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'ON']);

        NonStandardFkGuard::executeDdl($connection, self::DDL);

        static::assertSame([
            self::DDL,
            'SET SESSION restrict_fk_on_non_standard_key = OFF',
            self::DDL,
            'SET SESSION restrict_fk_on_non_standard_key = ON',
        ], $statements);
    }

    public function testRestoresGuardWhenRetryFails(): void
    {
        $retryFailure = new FakeDriverException(self::ER_DROP_INDEX_FK, 'Retry still failed');
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(4))->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$statements, $retryFailure): int {
                $statements[] = $sql;
                if ($sql === self::DDL) {
                    throw \count($statements) === 1
                        ? new FakeDriverException(self::ER_DROP_INDEX_FK, 'Initial failure')
                        : $retryFailure;
                }

                return 0;
            });
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'ON']);

        $thrown = null;
        try {
            NonStandardFkGuard::executeDdl($connection, self::DDL);
        } catch (DriverException $thrown) {
        }

        static::assertSame($retryFailure, $thrown);
        static::assertSame('SET SESSION restrict_fk_on_non_standard_key = ON', end($statements));
    }
}

/**
 * @internal
 */
class FakeDriverException extends DriverException
{
    public function __construct(int $errorCode, string $message)
    {
        $this->code = $errorCode;
        $this->message = $message;
    }
}
