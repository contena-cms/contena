<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReport;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Contena\Core\Framework\Api\Command\StoreApiSchemaMigrationReportCommand;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(StoreApiSchemaMigrationReportCommand::class)]
class StoreApiSchemaMigrationReportCommandTest extends TestCase
{
    public function testCommandOutputsMigrationReport(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $commandTester->execute([], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();

        $report = json_decode($commandTester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($report);
        static::assertArrayHasKey('jsonOverridesPhpGenerated', $report);
        static::assertIsArray($report['jsonOverridesPhpGenerated']);
        static::assertArrayHasKey('phpGeneratedOnly', $report);
        static::assertIsArray($report['phpGeneratedOnly']);
        static::assertSame(['JsonOverrideEntity'], $report['jsonOverridesPhpGenerated']);
        static::assertSame(['TestEntityWithAssociations'], $report['phpGeneratedOnly']);
    }

    public function testCommandCanFailOnMigrationMismatches(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
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
                '/tmp/store-api-schema-migration-report.json',
                static::callback(static function (string $contents): bool {
                    $report = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

                    return \is_array($report)
                        && $report['jsonOverridesPhpGenerated'] === ['JsonOverrideEntity'];
                }),
            );

        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
            $filesystem,
        ));

        $commandTester->execute(['outfile' => '/tmp/store-api-schema-migration-report.json'], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();
        static::assertSame('', $commandTester->getDisplay());
    }

    public function testCommandFailsForInvalidScope(): void
    {
        $commandTester = new CommandTester(new StoreApiSchemaMigrationReportCommand(
            $this->createReporter($this->createReport()),
            $this->createDefinitionRegistry(),
        ));

        $exitCode = $commandTester->execute(['--scope' => 'extensions'], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('The scope option must be one of: core, all.', $commandTester->getDisplay());
    }

    private function createReporter(StoreApiSchemaMigrationReport $report): StoreApiSchemaMigrationReporter
    {
        $reporter = static::createStub(StoreApiSchemaMigrationReporter::class);
        $reporter->method('report')->willReturn($report);
        $reporter->method('getSupportedScopes')->willReturn([
            CoreStoreApiSchemaMigrationScopeProvider::SCOPE,
            AllStoreApiSchemaMigrationScopeProvider::SCOPE,
        ]);

        return $reporter;
    }

    private function createDefinitionRegistry(): ChannelDefinitionInstanceRegistry
    {
        $definitionRegistry = static::createStub(ChannelDefinitionInstanceRegistry::class);
        $definitionRegistry->method('getDefinitions')->willReturn([]);

        return $definitionRegistry;
    }

    private function createReport(): StoreApiSchemaMigrationReport
    {
        return new StoreApiSchemaMigrationReport(
            jsonOverridesPhpGenerated: ['JsonOverrideEntity'],
            phpGeneratedOnly: ['TestEntityWithAssociations'],
            jsonWithoutPhpGenerated: [],
        );
    }
}
