<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Frontend\DependencyInjection\FrontendMigrationReplacementCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FrontendMigrationReplacementCompilerPass::class)]
class FrontendMigrationReplacementCompilerPassTest extends TestCase
{
    public function testProcessing(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_8', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new FrontendMigrationReplacementCompilerPass());
        $container->compile();

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_8')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_8', $calls[0][1][0]);
        static::assertSame('Contena\Frontend\Migration\V6_8', $calls[0][1][1]);
    }
}
