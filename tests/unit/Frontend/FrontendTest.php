<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\DependencyInjection\TwigComponentBundlePass;
use Contena\Frontend\Framework\Twig\Components\TwigComponentRenderEventListener;
use Contena\Frontend\Frontend;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @internal
 */
#[CoversClass(Frontend::class)]
class FrontendTest extends TestCase
{
    public function testTemplatePriority(): void
    {
        static::assertSame(0, new Frontend()->getTemplatePriority());
    }

    public function testBuildRegistersTwigComponentCompilerPass(): void
    {
        $container = $this->buildContainer();
        new Frontend()->build($container);

        $passClasses = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses(),
        );

        static::assertContains(TwigComponentBundlePass::class, $passClasses);
    }

    public function testBuildSetsFrontendRootParameter(): void
    {
        $container = $this->buildContainer();
        $frontend = new Frontend();
        $frontend->build($container);

        static::assertTrue($container->hasParameter('frontendRoot'));
        static::assertSame($frontend->getPath(), $container->getParameter('frontendRoot'));
    }

    public function testTwigComponentListenerUsesOneExplicitEventTag(): void
    {
        $container = $this->buildContainer();
        new Frontend()->build($container);

        $definition = $container->getDefinition(TwigComponentRenderEventListener::class);
        static::assertFalse($definition->isAutoconfigured());
        static::assertCount(1, $definition->getTag('kernel.event_listener'));
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        foreach ($this->stubExtensions() as $extension) {
            $container->registerExtension($extension);
        }

        return $container;
    }

    /**
     * @return list<Extension>
     */
    private function stubExtensions(): array
    {
        $stub = static fn (string $alias): Extension => new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            /**
             * @throws void
             */
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            /**
             * @throws void
             */
            public function getAlias(): string
            {
                return $this->alias;
            }
        };

        return [
            $stub('framework'),
            $stub('twig'),
            $stub('twig_component'),
        ];
    }
}
