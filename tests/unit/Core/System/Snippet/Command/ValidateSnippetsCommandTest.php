<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Contena\Core\System\Snippet\Files\GenericSnippetFile;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\SnippetFileHandler;
use Contena\Core\System\Snippet\SnippetFixer;
use Contena\Core\System\Snippet\SnippetValidator;
use Contena\Core\System\Snippet\Struct\InvalidPluralizationCollection;
use Contena\Core\System\Snippet\Struct\MissingSnippetCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ValidateSnippetsCommand::class)]
class ValidateSnippetsCommandTest extends TestCase
{
    #[TestDox('Complete snippets are reported as valid')]
    public function testReportsValidSnippets(): void
    {
        $commandTester = $this->createCommandTester(new SnippetFileCollection(), []);

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('Snippets are valid!', $commandTester->getDisplay());
    }

    #[TestDox('Missing translations are listed per ISO and fail the command')]
    public function testReportsMissingSnippets(): void
    {
        [$collection, $jsonByPath] = $this->createIncompleteSnippetSetup();
        $snippetFixer = $this->createMock(SnippetFixer::class);
        $snippetFixer->expects($this->never())->method('fix');

        $commandTester = $this->createCommandTester($collection, $jsonByPath, $snippetFixer);

        static::assertSame(-1, $commandTester->execute([]));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Invalid snippets found!', $display);
        static::assertStringContainsString('checkout.finish', $display);
        static::assertStringContainsString('de', $display);
    }

    #[TestDox('The fix wizard asks for the missing translation and passes it to the fixer')]
    public function testFixWizardCollectsTranslations(): void
    {
        [$collection, $jsonByPath] = $this->createIncompleteSnippetSetup();
        $snippetFixer = $this->createMock(SnippetFixer::class);
        $snippetFixer
            ->expects($this->once())
            ->method('fix')
            ->willReturnCallback(function (MissingSnippetCollection $missingSnippets, InvalidPluralizationCollection $invalidPluralization): void {
                $this->assertCount(1, $missingSnippets);
                $this->assertSame('Kasse', $missingSnippets->first()?->getTranslation());
                $this->assertCount(0, $invalidPluralization);
            });

        $commandTester = $this->createCommandTester($collection, $jsonByPath, $snippetFixer);
        $commandTester->setInputs(['Kasse']);

        static::assertSame(Command::SUCCESS, $commandTester->execute(['--fix' => true]));
    }

    /**
     * One english snippet file with a translation the german file is missing.
     *
     * @return array{0: SnippetFileCollection, 1: array<string, array<string, mixed>>}
     */
    private function createIncompleteSnippetSetup(): array
    {
        $collection = new SnippetFileCollection([
            $this->createSnippetFile('en', '/snippets/frontend.en.json'),
            $this->createSnippetFile('de', '/snippets/frontend.de.json'),
        ]);

        $jsonByPath = [
            '/snippets/frontend.en.json' => ['checkout' => ['finish' => 'Checkout']],
            '/snippets/frontend.de.json' => [],
        ];

        return [$collection, $jsonByPath];
    }

    /**
     * @param array<string, array<string, mixed>> $jsonByPath
     */
    private function createCommandTester(
        SnippetFileCollection $collection,
        array $jsonByPath,
        ?SnippetFixer $snippetFixer = null
    ): CommandTester {
        return new CommandTester($this->createCommand($collection, $jsonByPath, $snippetFixer));
    }

    /**
     * @param array<string, array<string, mixed>> $jsonByPath
     */
    private function createCommand(
        SnippetFileCollection $collection,
        array $jsonByPath,
        ?SnippetFixer $snippetFixer = null
    ): ValidateSnippetsCommand {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);
        $snippetFileHandler->method('findAdministrationSnippetFiles')->willReturn([]);
        $snippetFileHandler->method('findFrontendSnippetFiles')->willReturn([]);
        $snippetFileHandler
            ->method('openJsonFile')
            ->willReturnCallback(static fn (string $path): array => $jsonByPath[$path] ?? []);

        $command = new ValidateSnippetsCommand(
            new SnippetValidator($collection, $snippetFileHandler, '/project'),
            $snippetFixer ?? static::createStub(SnippetFixer::class)
        );
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));

        return $command;
    }

    private function createSnippetFile(string $iso, string $path): GenericSnippetFile
    {
        return new GenericSnippetFile(
            'frontend.' . $iso,
            $path,
            $iso,
            'Contena',
            true,
            'Frontend'
        );
    }
}
