<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Elasticsearch\DependencyInjection\ElasticsearchMigrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(ElasticsearchMigrationCompilerPass::class)]
class ElasticsearchMigrationCompilerPassTest extends TestCase
{
    public function testCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_8', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new ElasticsearchMigrationCompilerPass());
        $container->compile();

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_8')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_8', $calls[0][1][0]);
        static::assertSame('Contena\Elasticsearch\Migration\V6_8', $calls[0][1][1]);
    }
}
