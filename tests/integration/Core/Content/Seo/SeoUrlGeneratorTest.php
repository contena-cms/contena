<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SeoUrlGeneratorTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    private SeoUrlGenerator $seoUrlGenerator;

    private SeoUrlRouteRegistry $seoUrlRouteRegistry;

    private IdsCollection $ids;

    private string $deLanguageId;

    private string $channelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
        $this->deLanguageId = $this->getDeDeLanguageId();

        $this->createBreadcrumbData();
        $channel = $this->createChannel([
            'navigationCategoryId' => $this->ids->get('rootCategory'),
        ]);

        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $contextFactory->create('', $channel['id']);
        $this->channelId = $channel['id'];

        $this->seoUrlGenerator = new SeoUrlGenerator(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            static::getContainer()->get('router.default'),
            static::getContainer()->get('request_stack'),
            static::getContainer()->get('contena.seo_url.twig'),
            static::getContainer()->get(TwigVariableParserFactory::class),
            new NullLogger(),
        );

        $this->seoUrlRouteRegistry = static::getContainer()->get(SeoUrlRouteRegistry::class);
    }

    /**
     * Checks whether the amount of generated URLs is correct. Empty SEO-URL
     * templates should lead to no SEO-URL being generated.
     */
    #[DataProvider('templateDataProvider')]
    public function testGenerateUrlCount(string $template, int $count, string $pathInfo): void
    {
        $id = $this->getValidCategoryId();

        $route = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $route,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );

        static::assertIsIterable($urls);
        static::assertCount($count, iterator_to_array($urls, false));
    }

    /**
     * Checks whether the SEO-URL path generated fits the expected template.
     */
    #[DataProvider('templateDataProvider')]
    public function testGenerateSeoPathInfo(string $template, int $count, string $pathInfo): void
    {
        $id = $this->getValidCategoryId();

        if ($pathInfo === 'id') {
            $pathInfo = $id;
        }

        $route = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $urls = $this->seoUrlGenerator->generate(
            [$id],
            $template,
            $route,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );

        static::assertIsIterable($urls);

        foreach ($urls as $url) {
            if ($pathInfo !== '') {
                static::assertStringEndsWith($pathInfo, $url->getSeoPathInfo());
            }
        }
    }

    /**
     * @return iterable<string, array{template: string, count: int, pathInfo: string}>
     */
    public static function templateDataProvider(): iterable
    {
        yield 'dynamic template renders one SEO URL' => [
            'template' => '{{ id }}',
            'count' => 1,
            'pathInfo' => 'id',
        ];
        yield 'static template renders one SEO URL' => [
            'template' => 'STATIC',
            'count' => 1,
            'pathInfo' => 'STATIC',
        ];
        yield 'empty template renders no SEO URLs' => [
            'template' => '',
            'count' => 0,
            'pathInfo' => '',
        ];
    }

    public function testTemplateWithCategoryAssociation(): void
    {
        $ids = new IdsCollection();

        $blog = new BlogBuilder($ids, 'blog')
            ->visibility($this->channelId)
            ->category('test category');

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        $blogIds = array_values($ids->getList(['blog']));
        $template = '{% if blog.categories %}{% for var in blog.categories %}{{ var.translated.name }}-{% endfor %}{% endif %}{{ blog.translated.name|lower }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestBlogSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($blogIds, $template, $route, Context::createDefaultContext(), $this->channelContext->getChannel());

        $expected = ['test-category-blog'];
        foreach ($result as $index => $seoUrl) {
            static::assertSame($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testTemplateWithCustomTwigExtension(): void
    {
        $ids = new IdsCollection();

        $blog = new BlogBuilder($ids, 'my blog')
            ->visibility($this->channelId);

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        $blogIds = array_values($ids->getList(['my blog']));
        $template = '{{ blog.translated.name|lastBigLetter }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestBlogSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($blogIds, $template, $route, Context::createDefaultContext(), $this->channelContext->getChannel());

        $expected = ['my-bloG'];
        foreach ($result as $index => $seoUrl) {
            static::assertSame($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testNotBeingStateful(): void
    {
        $categoryIds = $this->getCategoryIds(2);

        static::assertCount(2, $categoryIds, 'this is important for the test as you need more items to iterate for a context switch test');

        $seoRoute = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertNotNull($seoRoute);

        $firstRun = $this->seoUrlGenerator->generate(
            $categoryIds,
            'template first run',
            $seoRoute,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );
        $secondRun = $this->seoUrlGenerator->generate(
            $categoryIds,
            'template second run',
            $seoRoute,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );

        foreach ($firstRun as $url) {
            static::assertSame('template first run', $url->getSeoPathInfo());

            break;
        }

        // this changes the template of the twig state to second template
        foreach ($secondRun as $_) {
            break;
        }

        foreach ($firstRun as $url) {
            static::assertSame('template first run', $url->getSeoPathInfo());
        }
    }

    public function testErrorLogging(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var array<int|string, array<string, list<array<string, mixed>>>>
             */
            public array $logs = [];

            /**
             * @param int|string $level
             * @param array<string, mixed> $context
             *
             * @throws void
             */
            public function log(mixed $level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[$level][(string) $message][] = $context;
            }
        };
        $seoUrlGenerator = new SeoUrlGenerator(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            static::getContainer()->get('router.default'),
            static::getContainer()->get('request_stack'),
            static::getContainer()->get('contena.seo_url.twig'),
            static::getContainer()->get(TwigVariableParserFactory::class),
            $logger,
        );

        $seoRoute = $this->seoUrlRouteRegistry->findByRouteName(TestNavigationSeoUrlRoute::ROUTE_NAME);
        static::assertNotNull($seoRoute);

        $urls = $seoUrlGenerator->generate(
            [$this->getValidCategoryId()],
            // broken twig template
            '{% for part in category.seoBreadcrumb %}{{ part }}/',
            $seoRoute,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );

        // generator needs to be triggered to fail
        foreach ($urls as $_) {
            break;
        }

        static::assertNotSame([], $logger->logs);
        $logger->logs = [];

        $urls = $seoUrlGenerator->generate(
            [$this->getValidCategoryId()],
            // invalid twig context
            '{{ blog.id }}',
            $seoRoute,
            $this->channelContext->getContext(),
            $this->channelContext->getChannel()
        );

        // generator needs to be triggered to fail
        foreach ($urls as $_) {
            break;
        }

        static::assertNotSame([], $logger->logs);
    }

    private function createBreadcrumbData(): void
    {
        static::getContainer()->get('category.repository')->create([
            [
                'id' => $this->ids->create('rootCategory'),
                'translations' => [
                    ['name' => 'EN-Entry', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'DE-Entry', 'languageId' => $this->deLanguageId],
                ],
                'children' => [
                    [
                        'id' => Uuid::randomHex(),
                        'translations' => [
                            ['name' => 'EN-A', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                            ['name' => 'DE-A', 'languageId' => $this->deLanguageId],
                        ],
                        'children' => [
                            [
                                'id' => $this->ids->create('childCategory'),
                                'translations' => [
                                    ['name' => 'EN-B', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                                    ['name' => 'DE-B', 'languageId' => $this->deLanguageId],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @return list<string>
     */
    private function getCategoryIds(int $count): array
    {
        /** @var EntityRepository<CategoryCollection> $repository */
        $repository = static::getContainer()->get('category.repository');

        $criteria = new Criteria()->setLimit($count);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }
}
