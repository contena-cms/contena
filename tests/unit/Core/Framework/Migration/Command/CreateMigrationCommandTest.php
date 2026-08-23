<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\Command\CreateMigrationCommand;
use Contena\Core\Framework\Migration\MigrationException;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CreateMigrationCommand::class)]
class CreateMigrationCommandTest extends TestCase
{
    public function testExecuteThrowsExceptionIfNameContainsForbiddenCharacters(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'contenaVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['--name' => '%%%%'];

        $this->expectExceptionObject(MigrationException::invalidArgument('Migration name contains forbidden characters!'));

        $commandTester->execute($input);
    }

    public function testExecuteThrowsExceptionWhenDirectoryIsSpecifiedButNoNamespace(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'contenaVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['directory' => 'test-dir'];

        $this->expectExceptionObject(MigrationException::invalidArgument('Please specify both dir and namespace or none.'));

        $commandTester->execute($input);
    }

    public function testExecuteThrowsExceptionWhenPluginIsNotFound(): void
    {
        $command = new CreateMigrationCommand(
            new KernelPluginCollection(),
            'coreDir',
            'contenaVersion'
        );
        $commandTester = new CommandTester($command);

        $input = ['--plugin' => 'test-plugin'];

        $this->expectExceptionObject(MigrationException::pluginNotFound('test-plugin'));

        $commandTester->execute($input);
    }
}
