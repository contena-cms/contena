<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(TwigEnvironmentCompilerPass::class)]
class TwigEnvironmentCompilerPassTest extends TestCase
{
    public function testTwigServiceUsesContenaImplementationAndDefaultCacheDir(): void
    {
        $container = $this->createContainer(['cache' => false]);

        new TwigEnvironmentCompilerPass()->process($container);

        $twig = $container->getDefinition('twig');
        static::assertTrue($twig->isPublic());
        static::assertSame(TwigEnvironment::class, $twig->getClass());
        static::assertSame(
            [['method' => 'reset']],
            $twig->getTag('kernel.reset'),
        );

        static::assertSame(
            '/tmp/contena-cache/twig',
            $container->getParameter('twig.cache')
        );
    }

    public function testTwigServiceKeepsConfiguredCacheDir(): void
    {
        $container = $this->createContainer(['cache' => '/custom/twig-cache']);

        new TwigEnvironmentCompilerPass()->process($container);

        static::assertSame('/custom/twig-cache', $container->getParameter('twig.cache'));
    }

    /**
     * @param array<string, mixed> $twigOptions
     */
    private function createContainer(array $twigOptions): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', '/tmp/contena-cache');
        $container->setDefinition('twig', new Definition(Environment::class, [null, $twigOptions]));

        return $container;
    }
}
