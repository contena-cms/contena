<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Controller\NavigationController;
use Contena\Frontend\DependencyInjection\DisableTemplateCachePass;
use Contena\Frontend\DependencyInjection\FrontendMigrationReplacementCompilerPass;
use Contena\Frontend\DependencyInjection\TwigComponentBundlePass;
use Contena\Frontend\Framework\Captcha\HoneypotCaptcha;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Mcp\Tool\ThemeConfigTool;
use Contena\Frontend\Frontend;
use Contena\Frontend\System\Channel\ChannelAnalyticsLoader;
use Contena\Frontend\Theme\ThemeService;
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
        static::assertSame(0, (new Frontend())->getTemplatePriority());
    }

    public function testBuildRegistersCompilerPasses(): void
    {
        $container = $this->buildContainer();
        $frontend = new Frontend();
        $frontend->build($container);

        $passClasses = array_map(
            static fn (CompilerPassInterface $pass): string => $pass::class,
            $container->getCompilerPassConfig()->getPasses()
        );

        static::assertContains(DisableTemplateCachePass::class, $passClasses);
        static::assertContains(FrontendMigrationReplacementCompilerPass::class, $passClasses);
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

    public function testBuildLoadsServiceDefinitions(): void
    {
        $container = $this->buildContainer();
        (new Frontend())->build($container);

        static::assertTrue($container->has(FrontendCartFacade::class), 'services.xml');
        static::assertTrue($container->has(HoneypotCaptcha::class), 'captcha.xml');
        static::assertTrue($container->has(BlogPageSeoUrlRoute::class), 'seo.xml');
        static::assertTrue($container->has(NavigationController::class), 'controller.xml');
        static::assertTrue($container->has(ThemeService::class), 'theme.xml');
        static::assertTrue($container->has(ChannelAnalyticsLoader::class), 'system.xml');
        static::assertTrue($container->has(ThemeConfigTool::class), 'mcp.php');
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
