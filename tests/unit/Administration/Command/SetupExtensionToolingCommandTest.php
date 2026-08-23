<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Command\SetupExtensionToolingCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(SetupExtensionToolingCommand::class)]
class SetupExtensionToolingCommandTest extends TestCase
{
    use ExtensionToolingCommandTestBehaviour;

    public function testSetupCommandRunsTheSetupEntryScript(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withToolingStub: true);

        $tester = new CommandTester(new SetupExtensionToolingCommand($this->kernel(), $administrationRoot));
        $tester->execute(['tooling-args' => ['--check']]);

        $capture = $this->readToolingCapture($administrationRoot);
        static::assertStringEndsWith('scripts/extensionTooling/setup.ts', $capture['argv'][1]);
        static::assertContains('--check', $capture['argv']);

        $this->removeAdministrationRoot($administrationRoot);
    }

    public function testCommandDescriptionMarksTheToolingExperimental(): void
    {
        $command = new SetupExtensionToolingCommand($this->kernel(), null);

        static::assertStringContainsString('Generates TypeScript/ESLint configs', $command->getDescription());
    }
}
