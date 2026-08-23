<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\BundleFixture;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Twig\Extension\IconCacheTwigFilter;
use Contena\Frontend\Framework\Twig\IconExtension;
use Contena\Frontend\Frontend;
use Contena\Tests\Unit\Frontend\Controller\fixtures\TestFrontendController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
#[CoversClass(IconCacheTwigFilter::class)]
#[CoversClass(IconExtension::class)]
class IconCacheTwigFilterTest extends TestCase
{
    public function testFrontendRenderIconCacheEnabled(): void
    {
        $frontendBundleFileName = new \ReflectionClass(Frontend::class)->getFileName();
        static::assertNotFalse($frontendBundleFileName);

        $twig = $this->createFinder([
            new BundleFixture('FrontendTest', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('Frontend', \dirname($frontendBundleFileName)),
        ]);

        $container = $this->buildContainer();
        $container->set('twig', $twig);
        $container->set(TemplateFinder::class, $twig->getExtension(NodeExtension::class)->getFinder());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService
            ->expects($this->once())
            ->method('get')
            ->with('core.frontendSettings.iconCache', 'channel-id')
            ->willReturn(true);
        $container->set(SystemConfigService::class, $systemConfigService);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('channel-id');

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $channelContext,
            RequestTransformer::FRONTEND_URL => '/',
        ]);
        $container->get('request_stack')->push($request);

        $controller = new TestFrontendController();
        $controller->setContainer($container);

        $rendered = $controller->testRenderFrontend('@FrontendTest/test/base.html.twig');
        $content = str_replace(' ', '', $rendered->getContent() ?: '');

        static::assertSame(1, substr_count($content, '<defs><pathid="icons-solid-minus-large"'));
        static::assertSame(2, substr_count($content, 'xlink:href="#icons-solid-minus-large"'));
        static::assertSame(1, substr_count($content, '<defs><pathid="icons-solid-minus-small"'));
        static::assertSame(1, substr_count($content, '<defs><pathid="icons-solid-minus"'));
        static::assertSame(1, substr_count($content, '<defs><pathid="icons-default-minus"'));
        static::assertSame(3, substr_count($content, 'xlink:href="#icons-default-minus"'));
        static::assertSame(1, substr_count($content, '<defs><pathd="M10.0944'));
        static::assertSame(1, substr_count($content, 'id="icons-default-search"'));
        static::assertSame(1, substr_count($content, 'id="icons-default-file"'));
        static::assertStringContainsString(
            "<spanclass=\"iconicon-minus\">\n<svgxmlns=",
            $content
        );
        static::assertSame(8, substr_count($content, 'aria-hidden="true"'));
    }

    public function buildContainer(): ContainerInterface
    {
        $container = new ContainerBuilder();
        $container->set('request_stack', new RequestStack());
        $container->set('event_dispatcher', new EventDispatcher());

        $placeholder = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $placeholder->method('replace')->willReturnArgument(0);

        $container->set(SeoUrlPlaceholderHandlerInterface::class, $placeholder);

        $mediaUrlHandler = static::createStub(MediaUrlPlaceholderHandlerInterface::class);
        $mediaUrlHandler->method('replace')->willReturnArgument(0);

        $container->set(MediaUrlPlaceholderHandlerInterface::class, $mediaUrlHandler);

        return $container;
    }

    /**
     * @param Bundle[] $bundles
     */
    private function createFinder(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Frontend/Resources/views');

        /** @var BundleFixture $bundle */
        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
            if (\is_dir($directory . '/../app/frontend/dist')) {
                $loader->addPath($directory . '/../app/frontend/dist', $bundle->getName());
            }
        }

        $twig = new Environment($loader, ['cache' => false]);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')
            ->willReturn($bundles);

        $builder = static::createStub(BundleHierarchyBuilder::class);
        $builder
            ->method('buildNamespaceHierarchy')
            ->willReturn(['Frontend' => 0]);

        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('twig_test_', true),
            new NamespaceHierarchyBuilder([
                $builder,
            ]),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->getExtension(NodeExtension::class)->getFinder();

        $twig->addExtension(new IconCacheTwigFilter());
        $twig->addExtension(new IconExtension());

        return $twig;
    }
}
