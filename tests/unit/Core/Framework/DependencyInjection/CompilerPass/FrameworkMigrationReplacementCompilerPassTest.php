<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\FrameworkMigrationReplacementCompilerPass;
use Contena\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FrameworkMigrationReplacementCompilerPass::class)]
class FrameworkMigrationReplacementCompilerPassTest extends TestCase
{
    public function testProcessing(): void
    {
        $container = new ContainerBuilder();
        $container->register(MigrationSource::class . '.core.V6_8', MigrationSource::class)->setPublic(true);

        $container->addCompilerPass(new FrameworkMigrationReplacementCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 0);
        $container->compile(false);

        $calls = $container->getDefinition(MigrationSource::class . '.core.V6_8')->getMethodCalls();
        static::assertCount(1, $calls);

        static::assertSame('addDirectory', $calls[0][0]);
        static::assertStringContainsString('Migration/V6_8', $calls[0][1][0]);
        static::assertSame('Contena\Core\Migration\V6_8', $calls[0][1][1]);
    }
}
