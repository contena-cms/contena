<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Kernel;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\BundleFixture;
use Contena\Frontend\Framework\Twig\Extension\ConfigExtension;
use Contena\Frontend\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Contena\Frontend\Framework\Twig\ThumbnailExtension;
use Contena\Frontend\Frontend;
use Contena\Frontend\Theme\AbstractResolvedConfigLoader;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
use Contena\Frontend\Theme\ThemeScripts;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
class ThumbnailExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersCorrectImageHtml(): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/thumbnail-default.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateChannelContext(),
        ]);

        // Expect the image to be rendered with the default attributes
        static::assertStringContainsString('src="https://contena.local/media/cute-cat.webp"', $result);
        static::assertStringContainsString('alt="Very cute cat alt"', $result);
        static::assertStringContainsString('title="Very cute cat title"', $result);
        static::assertStringContainsString('loading="eager"', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersDecorativeImageWithEmptyAltAttr(): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/thumbnail-decorative.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateChannelContext(),
        ]);

        // Expect the image to be rendered with empty alt attribute
        static::assertStringContainsString('alt=""', $result);

        // Other empty attributes like title are omitted
        static::assertStringNotContainsString('title=""', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersImageWithoutAltAttr(): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/thumbnail-alt-false.html.twig', [
            'media' => $this->createExampleMedia(),
            'context' => Generator::generateChannelContext(),
        ]);

        // Expect the image to be rendered without alt attribute
        static::assertStringNotContainsString('alt=', $result);

        // Other attributes are set
        static::assertStringContainsString('title="Very cute cat title"', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersSrcsetAttrWhenMediaThumbnailsAreGiven(): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/thumbnail-default.html.twig', [
            'media' => $this->createExampleMediaWithThumbnails([280, 400, 800, 1920]),
            'context' => Generator::generateChannelContext(),
        ]);

        static::assertStringContainsString('src="https://contena.local/media/cute-cat.webp"', $result);
        static::assertStringContainsString('srcset="https://contena.local/thumbnail/cute-cat_800x800.webp 800w, https://contena.local/thumbnail/cute-cat_400x400.webp 400w, https://contena.local/thumbnail/cute-cat_280x280.webp 280w, https://contena.local/thumbnail/cute-cat_1920x1920.webp 1920w"', $result);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function testSwThumbnailsRendersSizesAttrWithValueForEveryBreakpoint(): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/thumbnail-with-columns.html.twig', [
            'media' => $this->createExampleMediaWithThumbnails([280, 400, 800, 1920]),
            'context' => Generator::generateChannelContext(),
        ]);

        // Regression test for https://github.com/contena-cms/contena/issues/16710.
        // Every breakpoint entry in the auto-generated sizes attribute must carry
        // a non-empty value. Before the fix the xxl entry was missing and produced
        // "(min-width: ...px) ," in the rendered output.
        $sizes = self::getSizesAttribute($result);

        self::assertSizesAttributeHasValueForEveryBreakpoint($sizes);
    }

    /**
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[DataProvider('blogBoxLayoutProvider')]
    public function testBlogBoxThumbnailSizesContainXxlBreakpointValue(string $layout): void
    {
        $result = $this->renderTemplate('@Frontend/frontend/blog-box-thumbnail-sizes.html.twig', [
            'layout' => $layout,
            'media' => $this->createExampleMediaWithThumbnails([280, 400, 800, 1920]),
            'context' => Generator::generateChannelContext(),
            // with v6.8.0.0 breakpoint defaults are only resolved for theme-bound renders
            'themeId' => Uuid::randomHex(),
        ]);

        $sizes = self::getSizesAttribute($result);

        self::assertSizesAttributeHasValueForEveryBreakpoint($sizes);
        static::assertStringContainsString('(min-width: 1400px) 280px', $sizes);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blogBoxLayoutProvider(): iterable
    {
        yield 'standard layout' => ['standard'];
        yield 'image layout' => ['image'];
    }

    private static function getSizesAttribute(string $result): string
    {
        static::assertSame(1, preg_match('/\ssizes="(?P<sizes>[^"]+)"/', $result, $matches), 'sizes attribute is missing');
        static::assertIsString($matches['sizes']);

        return $matches['sizes'];
    }

    private static function assertSizesAttributeHasValueForEveryBreakpoint(string $sizes): void
    {
        $entries = array_map('trim', explode(',', $sizes));
        $fallback = array_pop($entries);

        static::assertNotSame('', $fallback, 'sizes fallback entry is empty');

        foreach ($entries as $i => $entry) {
            static::assertMatchesRegularExpression(
                '/^\(min-width:[^)]*\)\s+\S+/',
                $entry,
                \sprintf('Sizes entry #%d has an empty value: "%s"', $i, $entry)
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws SyntaxError
     * @throws \Throwable
     * @throws Exception
     * @throws RuntimeError
     * @throws LoaderError
     */
    private function renderTemplate(string $templatePath, array $data): string
    {
        $frontendBundleFileName = (new \ReflectionClass(Frontend::class))->getFileName();
        static::assertNotFalse($frontendBundleFileName);

        [$twig, $templateFinder] = $this->createFinder([
            new BundleFixture('FrontendTest', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('Frontend', \dirname($frontendBundleFileName)),
        ]);

        $templatePath = $templateFinder->find($templatePath);
        $template = $twig->loadTemplate($twig->getTemplateClass($templatePath), $templatePath);

        return $template->render($data);
    }

    private function createExampleMedia(): MediaEntity
    {
        $media = new MediaEntity();
        $media->setId('test-media-id');
        $media->setUrl('https://contena.local/media/cute-cat.webp');
        $media->setPath('media/cute-cat.webp');
        $media->setTranslated([
            'title' => 'Very cute cat title',
            'alt' => 'Very cute cat alt',
        ]);

        return $media;
    }

    /**
     * @param array<int> $thumbnailSizes
     */
    private function createExampleMediaWithThumbnails(array $thumbnailSizes): MediaEntity
    {
        $media = $this->createExampleMedia();

        $media->setThumbnails($this->createThumbnails($thumbnailSizes));

        return $media;
    }

    /**
     * @param array<int> $thumbnailSizes
     */
    private function createThumbnails(array $thumbnailSizes): MediaThumbnailCollection
    {
        $thumbnailCollection = new MediaThumbnailCollection();

        foreach ($thumbnailSizes as $size) {
            $thumbnail = new MediaThumbnailEntity();
            $thumbnail->setId('thumb-' . $size);
            $thumbnail->setWidth($size);
            $thumbnail->setHeight($size);
            $thumbnail->setUrl('https://contena.local/thumbnail/cute-cat_' . $size . 'x' . $size . '.webp');
            $thumbnailCollection->add($thumbnail);
        }

        return $thumbnailCollection;
    }

    /**
     * @param BundleFixture[] $bundles
     *
     * @throws LoaderError
     * @throws Exception
     *
     * @return array{0: Environment, 1: TemplateFinder}
     */
    private function createFinder(array $bundles): array
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Frontend/Resources/views');

        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new Environment($loader);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')
            ->willReturn($bundles);

        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('twig_test_', true),
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    static::getContainer()->get(Connection::class)
                ),
            ]),
            $scopeDetector,
        );

        // Needed for the ConfigExtension, so the theme_config('breakpoint.sm') calls return the actual breakpoints.
        $templateConfigAccessor = new TemplateConfigAccessor(
            static::createStub(SystemConfigService::class),
            new ThemeConfigValueAccessor(
                static::createStub(AbstractResolvedConfigLoader::class),
                static::createStub(CacheTagCollector::class)
            ),
            static::createStub(ThemeScripts::class),
            'test',
            [],
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->getExtension(NodeExtension::class)->getFinder();
        $twig->addExtension(new ThumbnailExtension($templateFinder));
        $twig->addExtension(new FeatureFlagExtension());

        // url encoder and theme_config are used inside the thumbnail.html.twig template
        $twig->addExtension(new ConfigExtension($templateConfigAccessor));
        $twig->addExtension(new UrlEncodingTwigFilter());

        return [$twig, $templateFinder];
    }
}
