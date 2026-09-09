<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Command;

use Contena\Core\Framework\App\Command\ChangeInstallationIdCommand;
use Contena\Core\Framework\App\InstallationIdChangeResolver\Resolver;
use Contena\Core\Framework\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChangeInstallationIdCommand::class)]
class ChangeInstallationIdCommandTest extends TestCase
{
    public function testChoosesTheRightStrategyForChangingTheInstallationId(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeInstallationIdCommand($urlChangeStrategy)
        );

        $commandTester->setInputs(['testStrategy']);
        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('Choose what strategy should be applied when changing the installation ID?', $commandTester->getDisplay());
        static::assertStringContainsString('testStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('secondStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }

    public function testChangeInstallationIdWithProvidedStrategy(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeInstallationIdCommand($urlChangeStrategy)
        );

        $commandTester->execute(['strategy' => 'testStrategy']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }

    public function testFailsIfChosenStrategyDoesNotExist(): void
    {
        $urlChangeStrategy = $this->createMock(Resolver::class);
        $urlChangeStrategy->expects($this->once())
            ->method('getAvailableStrategies')
            ->willReturn([
                'testStrategy' => 'test Description',
                'secondStrategy' => 'second Description',
            ]);

        $urlChangeStrategy->expects($this->once())
            ->method('resolve')
            ->with(
                'testStrategy',
                static::isInstanceOf(Context::class)
            );

        $commandTester = new CommandTester(
            new ChangeInstallationIdCommand($urlChangeStrategy)
        );

        $commandTester->setInputs(['testStrategy']);
        $commandTester->execute(['strategy' => 'doesNotExist']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[NOTE] Strategy with name: "doesNotExist" not found.', $commandTester->getDisplay());
        static::assertStringContainsString('Choose what strategy should be applied when changing the installation ID?', $commandTester->getDisplay());
        static::assertStringContainsString('testStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('secondStrategy', $commandTester->getDisplay());
        static::assertStringContainsString('[OK] Strategy "testStrategy" was applied successfully', $commandTester->getDisplay());
    }
}
