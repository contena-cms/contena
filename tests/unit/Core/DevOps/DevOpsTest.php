<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\DevOps;
use Contena\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Contena\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Contena\Core\DevOps\System\Command\SystemRestoreDatabaseCommand;
use Contena\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(DevOps::class)]
class DevOpsTest extends TestCase
{
    /**
     * @param list<class-string> $expectedServices
     * @param list<class-string> $unexpectedServices
     */
    #[DataProvider('buildDataProvider')]
    public function testBuildLoadsServices(string $environment, array $expectedServices, array $unexpectedServices): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        $bundle = new DevOps();
        $bundle->build($container);

        foreach ($expectedServices as $service) {
            static::assertTrue($container->has($service), \sprintf('Expected service "%s" to be registered', $service));
        }

        foreach ($unexpectedServices as $service) {
            static::assertFalse($container->has($service), \sprintf('Expected service "%s" NOT to be registered', $service));
        }
    }

    /**
     * @return \Generator<string, array{environment: string, expectedServices: list<class-string>, unexpectedServices: list<class-string>}>
     */
    public static function buildDataProvider(): \Generator
    {
        $baseServices = [
            SyncComposerVersionCommand::class,
        ];

        $e2eOnlyServices = [
            SystemDumpDatabaseCommand::class,
            SystemRestoreDatabaseCommand::class,
        ];

        yield 'production environment' => [
            'environment' => 'prod',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'test environment' => [
            'environment' => 'test',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'staging environment' => [
            'environment' => 'staging',
            'expectedServices' => $baseServices,
            'unexpectedServices' => [...$e2eOnlyServices, MakeCoverageTestCommand::class],
        ];

        yield 'e2e environment' => [
            'environment' => 'e2e',
            'expectedServices' => [...$baseServices, ...$e2eOnlyServices],
            'unexpectedServices' => [MakeCoverageTestCommand::class],
        ];

        yield 'dev environment' => [
            'environment' => 'dev',
            'expectedServices' => [...$baseServices, MakeCoverageTestCommand::class],
            'unexpectedServices' => $e2eOnlyServices,
        ];
    }
}
