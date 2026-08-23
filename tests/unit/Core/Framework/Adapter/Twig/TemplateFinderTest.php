<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\ConfigurableFilesystemCache;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\LoaderInterface;

/**
 * @internal
 */
#[CoversClass(TemplateFinder::class)]
class TemplateFinderTest extends TestCase
{
    private NamespaceHierarchyBuilder&MockObject $hierarchyBuilder;

    private LoaderInterface&Stub $loader;

    private TemplateFinder $finder;

    private TemplateScopeDetector&MockObject $templateScopeDetector;

    private Environment&MockObject $twig;

    protected function setUp(): void
    {
        $this->hierarchyBuilder = $this->createMock(NamespaceHierarchyBuilder::class);
        $this->loader = static::createStub(LoaderInterface::class);
        $this->templateScopeDetector = $this->createMock(TemplateScopeDetector::class);
        $this->twig = $this->createMock(Environment::class);
        $this->finder = new TemplateFinder(
            $this->twig,
            $this->loader,
            '',
            $this->hierarchyBuilder,
            $this->templateScopeDetector
        );
    }

    #[DataProvider('templateNameProvider')]
    public function testGetTemplateName(string $input, string $expectation): void
    {
        $this->hierarchyBuilder->expects($this->never())->method('buildHierarchy');
        $this->templateScopeDetector->expects($this->never())->method('getScopes');
        $this->twig->expects($this->never())->method('getCache');

        static::assertSame($expectation, $this->finder->getTemplateName($input));
    }

    /**
     * @param array<int, string> $templateExists
     * @param array<string, bool> $bundles
     */
    #[DataProvider('bundleTemplatesMappingProvider')]
    public function testFind(string $template, bool $ignoreMissing, array $templateExists, array $bundles, ?string $source = null, ?string $expectedTemplate = null): void
    {
        if ($expectedTemplate === null && $ignoreMissing === false) {
            static::expectException(LoaderError::class);
        }

        $templatePath = $this->finder->getTemplateName($template);

        $map = [];

        foreach ($templateExists as $bundleName) {
            $map[] = '@' . $bundleName . '/' . $templatePath;
        }

        $this->loader->method('exists')->willReturnCallback(static fn (string $template) => \in_array($template, $map, true));

        $this->hierarchyBuilder->expects($this->once())->method('buildHierarchy')->willReturn($bundles);
        // find() checks the cache on the non-throwing path only; getScopes is reached solely when the cache is a FilesystemCache (not here).
        $this->twig->expects($this->atMost(1))->method('getCache');
        $this->templateScopeDetector->expects($this->never())->method('getScopes');

        $foundTemplate = $this->finder->find($template, $ignoreMissing, $source);

        static::assertSame($expectedTemplate, $foundTemplate);
    }

    public function testFindModifiesCache(): void
    {
        $this->hierarchyBuilder->expects($this->once())->method('buildHierarchy');
        $this->twig->expects($this->once())->method('getCache')->willReturn(static::createStub(FilesystemCache::class));
        $this->twig->expects($this->once())->method('setCache')->with(static::callback(static function (ConfigurableFilesystemCache $cache) {
            $hash = $cache->generateKey('foo', 'bar');
            $cache->setTemplateScopes(['foo']);

            // template scope has been set
            static::assertSame($hash, $cache->generateKey('foo', 'bar'));

            // config hash had been set as well
            $cache->setConfigHash('');

            return $hash !== $cache->generateKey('foo', 'bar');
        }));
        $this->templateScopeDetector->expects($this->once())->method('getScopes')->willReturn(['foo']);
        $this->finder->find('', true);
    }

    /**
     * @return iterable<string, array<int, string>>
     */
    public static function templateNameProvider(): iterable
    {
        yield 'with @' => [
            '@Framework/profile.html.twig',
            'profile.html.twig',
        ];

        yield 'without @' => [
            'Framework/stoplightio.html.twig',
            'Framework/stoplightio.html.twig',
        ];
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function bundleTemplatesMappingProvider(): iterable
    {
        $coreBundles = [
            'Elasticsearch' => true,
            'Frontend' => true,
            'Administration' => true,
            'Profiling' => true,
            'CtTheme' => true,
            'Framework' => true,
        ];

        yield 'template not found with ignoreMissing' => [
            '@Framework/non_existing_template.html.twig',
            true,
            [],
            [],
            null,
            'non_existing_template.html.twig',
        ];

        yield 'template not found without ignoreMissing' => [
            '@Framework/non_existing_template.html.twig',
            false,
            [],
            [],
            null,
            null,
        ];

        yield 'find correct template with source' => [
            '@Framework/profile.html.twig',
            false,
            [
                'Framework',
                'CtTheme',
            ],
            $coreBundles,
            '@CtTheme/stoplightio.html.twig',
            '@CtTheme/profile.html.twig',
        ];

        yield 'find correct template without source' => [
            '@Framework/stoplightio.html.twig',
            false,
            [
                'CtTheme',
                'Framework',
            ],
            $coreBundles,
            null,
            '@CtTheme/stoplightio.html.twig',
        ];

        // @CtTheme/profile.html.twig is found even when the source is @Framework/stoplightio.html.twig
        yield 'find correct template with same source with input template' => [
            '@Framework/profile.html.twig',
            false,
            [
                'Framework',
                'CtTheme',
            ],
            $coreBundles,
            '@Framework/stoplightio.html.twig',
            '@CtTheme/profile.html.twig',
        ];

        yield 'return original template if template not found' => [
            '@Framework/custom.html.twig',
            true,
            [
            ],
            $coreBundles,
            '@Framework/stoplightio.html.twig',
            'custom.html.twig',
        ];

        yield 'throw error if template not found' => [
            '@Framework/custom.html.twig',
            false,
            [
            ],
            $coreBundles,
            '@Framework/stoplightio.html.twig',
            null,
        ];
    }
}
