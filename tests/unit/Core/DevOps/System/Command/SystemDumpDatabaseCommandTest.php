<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @phpstan-import-type Params from DriverManager
 */
#[CoversClass(SystemDumpDatabaseCommand::class)]
class SystemDumpDatabaseCommandTest extends TestCase
{
    private const string DUMP_DIR = '/tmp/ct-dump-test';
    private const string DB_NAME = 'contena';
    private const array DB_PARAMS = ['user' => 'root', 'host' => 'localhost', 'port' => 3306];

    private Connection&Stub $connection;

    private Filesystem&MockObject $filesystem;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabase')->willReturn(self::DB_NAME);
        $this->connection->method('getParams')->willReturn(self::DB_PARAMS);

        $this->filesystem = $this->createMock(Filesystem::class);
    }

    /**
     * @param Params $connectionParams
     * @param list<string> $ignoreTables
     * @param list<string> $expectedCmdParts
     */
    #[DataProvider('executeDumpProvider')]
    public function testExecuteBuildsCorrectCommand(
        array $connectionParams,
        string $dbName,
        array $ignoreTables,
        array $expectedCmdParts,
    ): void {
        $capturedCommands = [];

        $mkdirProcess = static::createStub(Process::class);
        $mkdirProcess->method('mustRun')->willReturnSelf();

        $dumpProcess = static::createStub(Process::class);
        $dumpProcess->method('mustRun')->willReturnSelf();
        $dumpProcess->method('getOutput')->willReturn('-- SQL dump content');

        $processFactory = static function (array $cmd) use (&$capturedCommands, $mkdirProcess, $dumpProcess): Process {
            $capturedCommands[] = $cmd;

            return $cmd[0] === 'mkdir' ? $mkdirProcess : $dumpProcess;
        };

        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabase')->willReturn($dbName);
        $this->connection->method('getParams')->willReturn($connectionParams);

        $this->filesystem->expects($this->once())->method('dumpFile');

        $tester = new CommandTester(new SystemDumpDatabaseCommand(self::DUMP_DIR, $this->connection, $processFactory, $this->filesystem));
        $tester->execute(['--ignore-table' => $ignoreTables]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame(['mkdir', '-p', self::DUMP_DIR], $capturedCommands[0]);

        $dumpCmd = $capturedCommands[1];
        foreach ($expectedCmdParts as $part) {
            static::assertContains($part, $dumpCmd);
        }
    }

    /**
     * @return \Generator<string, array{connectionParams: Params, dbName: string, ignoreTables: list<string>, expectedCmdParts: list<string>}>
     */
    public static function executeDumpProvider(): \Generator
    {
        yield 'basic connection without password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306],
            'dbName' => 'contena',
            'ignoreTables' => [],
            'expectedCmdParts' => ['mysqldump', '-u', 'root', '-h', 'localhost', '--port=3306', 'contena'],
        ];

        yield 'connection with password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306, 'password' => 's3cr3t'],
            'dbName' => 'contena',
            'ignoreTables' => [],
            'expectedCmdParts' => ['mysqldump', '-ps3cr3t', 'contena'],
        ];

        yield 'ignore tables are appended to command' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306],
            'dbName' => 'contena',
            'ignoreTables' => ['enqueue', 'log_entry'],
            'expectedCmdParts' => ['--ignore-table=contena.enqueue', '--ignore-table=contena.log_entry'],
        ];
    }

    public function testDefaultProcessFactoryIsUsedWhenNotProvided(): void
    {
        // This test never executes the command, so the shared filesystem mock is unused here.
        $this->filesystem->expects($this->never())->method('dumpFile');

        $command = new SystemDumpDatabaseCommand(
            self::DUMP_DIR,
            $this->connection,
        );

        static::assertSame('system:dump', $command->getName());
    }

    public function testExecuteWritesPreambleAndAppendsDumpOutput(): void
    {
        $expectedPath = self::DUMP_DIR . '/localhost_' . self::DB_NAME . '.sql';
        $dumpOutput = '-- dump';

        $dumpProcess = static::createStub(Process::class);
        $dumpProcess->method('mustRun')->willReturnSelf();
        $dumpProcess->method('getOutput')->willReturn($dumpOutput);

        $mkdirProcess = static::createStub(Process::class);
        $mkdirProcess->method('mustRun')->willReturnSelf();

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($expectedPath, 'SET unique_checks=0;SET foreign_key_checks=0;');

        $this->filesystem->expects($this->once())
            ->method('appendToFile')
            ->with($expectedPath, $dumpOutput);

        $command = new SystemDumpDatabaseCommand(
            self::DUMP_DIR,
            $this->connection,
            static fn (array $cmd): Process => $cmd[0] === 'mkdir' ? $mkdirProcess : $dumpProcess,
            $this->filesystem,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
