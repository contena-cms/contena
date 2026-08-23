<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiSchemaMigrationReport;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiSchemaMigrationReporter;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\Command\ChannelApiSchemaMigrationReportCommand;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ChannelApiSchemaMigrationReportCommand::class)]
class ChannelApiSchemaMigrationReportCommandTest extends TestCase
{
    public function testCommandOutputsMigrationReport(): void
    {
        $commandTester = new CommandTester(new ChannelApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $commandTester->execute([], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();

        $report = json_decode($commandTester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($report);
        static::assertArrayHasKey('jsonOverridesPhpGenerated', $report);
        static::assertIsArray($report['jsonOverridesPhpGenerated']);
        static::assertArrayHasKey('phpGeneratedOnlyWithoutAllowlist', $report);
        static::assertIsArray($report['phpGeneratedOnlyWithoutAllowlist']);
        static::assertSame(['JsonOverrideEntity'], $report['jsonOverridesPhpGenerated']);
        static::assertSame(['TestEntityWithAssociations'], $report['phpGeneratedOnlyWithoutAllowlist']);
    }

    public function testCommandCanFailOnMigrationMismatches(): void
    {
        $commandTester = new CommandTester(new ChannelApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--fail-on-mismatch' => true], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testCommandWritesReportToFile(): void
    {
        $filesystem = static::createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('dumpFile')
            ->with(
                '/tmp/channel-api-schema-migration-report.json',
                static::callback(static function (string $contents): bool {
                    $report = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

                    return \is_array($report)
                        && $report['jsonOverridesPhpGenerated'] === ['JsonOverrideEntity'];
                }),
            );

        $commandTester = new CommandTester(new ChannelApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
            $filesystem,
        ));

        $commandTester->execute(['outfile' => '/tmp/channel-api-schema-migration-report.json'], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();
        static::assertSame('', $commandTester->getDisplay());
    }

    public function testCommandFailsForInvalidScope(): void
    {
        $commandTester = new CommandTester(new ChannelApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--scope' => 'extensions'], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('The scope option must be one of: core, all.', $commandTester->getDisplay());
    }

    private function createReporter(ChannelApiSchemaMigrationReport $report): ChannelApiSchemaMigrationReporter
    {
        $reporter = static::createStub(ChannelApiSchemaMigrationReporter::class);
        $reporter->method('report')->willReturn($report);
        $reporter->method('getSupportedScopes')->willReturn([
            CoreChannelApiSchemaMigrationScopeProvider::SCOPE,
            AllChannelApiSchemaMigrationScopeProvider::SCOPE,
        ]);

        return $reporter;
    }

    private function createDefinitionRegistry(): DefinitionInstanceRegistry
    {
        $definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn([]);

        return $definitionRegistry;
    }

    private function createReport(): ChannelApiSchemaMigrationReport
    {
        return new ChannelApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
            phpGeneratedOnly: [],
            phpGeneratedOnlyAllowed: [],
            phpGeneratedOnlyWithoutAllowlist: ['TestEntityWithAssociations'],
            jsonWithoutPhpGenerated: [],
            allowlistWithoutPhpGeneratedOnlySchema: [],
            allowlistWithoutPhpGeneratedSchema: [],
        );
    }
}
