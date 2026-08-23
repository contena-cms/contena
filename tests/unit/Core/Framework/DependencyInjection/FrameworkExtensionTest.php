<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\Configuration;
use Contena\Core\Framework\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(FrameworkExtension::class)]
#[CoversClass(Configuration::class)]
class FrameworkExtensionTest extends TestCase
{
    public function testCacheCompressionConfigSetsParameters(): void
    {
        $container = new ContainerBuilder();

        new FrameworkExtension()->load([
            [
                'cache' => [
                    'compress' => false,
                    'compression_method' => 'deflate',
                ],
            ],
        ], $container);

        static::assertFalse($container->getParameter('contena.cache.compress'));
        static::assertSame('deflate', $container->getParameter('contena.cache.compression_method'));
    }
}
