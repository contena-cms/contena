<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Content\Test\TestNavigationSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class SeoUrlGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

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

    public function testVariantInheritance(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->insert('seo_url_template', [
            'id' => Uuid::randomBytes(),
            'route_name' => TestBlogSeoUrlRoute::ROUTE_NAME,
            'entity_name' => BlogDefinition::ENTITY_NAME,
            'template' => '{{ blog.translated.name }}/{{ blog.blogNumber }}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $ids = new IdsCollection();
        $blog = (new BlogBuilder($ids, 'parent'))
            ->price(100)
            ->visibility($this->channelId)
            ->variant(
                (new BlogBuilder($ids, 'red'))
                    ->tax(null)
                    ->name('red')
                    ->build()
            )
            ->variant(
                (new BlogBuilder($ids, 'green'))
                    ->name(null)
                    ->build()
            );

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        static::getContainer()->get(SeoUrlUpdater::class)->update(TestBlogSeoUrlRoute::ROUTE_NAME, array_values($ids->getList(['parent', 'red', 'green'])));

        $urls = $connection
            ->fetchAllAssociative(
                'SELECT LOWER(HEX(foreign_key)) as foreign_key, seo_path_info FROM seo_url WHERE route_name = :route AND foreign_key IN (:ids) AND channel_id = :channel',
                [
                    'route' => TestBlogSeoUrlRoute::ROUTE_NAME,
                    'ids' => Uuid::fromHexToBytesList($ids->getList(['parent', 'red', 'green'])),
                    'channel' => Uuid::fromHexToBytes($this->channelId),
                ],
                ['ids' => ArrayParameterType::BINARY]
            );

        $urls = FetchModeHelper::keyPair($urls);

        static::assertCount(3, $urls);
        static::assertArrayHasKey($ids->get('parent'), $urls);
        static::assertArrayHasKey($ids->get('green'), $urls);
        static::assertArrayHasKey($ids->get('red'), $urls);

        // name = parent | number = parent
        static::assertSame('parent/parent', $urls[$ids->get('parent')]);

        // name = red | number = red
        static::assertSame('red/red', $urls[$ids->get('red')]);

        // name = parent | number = green
        static::assertSame('parent/green', $urls[$ids->get('green')]);
    }

    public function testTemplateWithMultipleVariantOptions(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'parent'))
            ->price(100)
            ->visibility($this->channelId)
            ->variant(
                (new BlogBuilder($ids, 'redBlog'))
                    ->name('tshirt')
                    ->option('red', 'color')
                    ->build()
            )
            ->variant(
                (new BlogBuilder($ids, 'greenBlog'))
                    ->name('tshirt')
                    ->option('green', 'color', 1)
                    ->option('xl', 'size', 2)
                    ->build()
            );

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        $blogIds = array_values($ids->getList(['parent', 'redBlog', 'greenBlog']));
        $template = '{{ blog.translated.name|lower }}{% if blog.options %}{% for var in blog.options|sort((a,b)=> a.position <=> b.position) %}-{{ var.name }}{% endfor %}{% endif %}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestBlogSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($blogIds, $template, $route, Context::createDefaultContext(), $this->channelContext->getChannel());

        $expected = ['parent', 'tshirt-red', 'tshirt-green-xl'];
        foreach ($result as $index => $seoUrl) {
            static::assertSame($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testTemplateWithMultipleAssociations(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'blog'))
            ->price(100)
            ->visibility($this->channelId)
            ->manufacturer('contena')
            ->category('test category');

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        $blogIds = array_values($ids->getList(['blog']));
        $template = '{% if blog.categories %}{% for var in blog.categories %}{{ var.translated.name }}-{% endfor %}{% endif %}{{ blog.manufacturer.translated.name }}-{{ blog.translated.name|lower }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestBlogSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($blogIds, $template, $route, Context::createDefaultContext(), $this->channelContext->getChannel());

        $expected = ['test-category-contena-blog'];
        foreach ($result as $index => $seoUrl) {
            static::assertSame($expected[$index], $seoUrl->getSeoPathInfo());
        }
    }

    public function testTemplateWithCustomTwigExtension(): void
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'my blog'))
            ->price(100)
            ->visibility($this->channelId);

        static::getContainer()->get('blog.repository')
            ->create([$blog->build()], Context::createDefaultContext());

        $blogIds = array_values($ids->getList(['blog']));
        $template = '{{ blog.translated.name|lastBigLetter }}';
        $route = $this->seoUrlRouteRegistry->findByRouteName(TestBlogSeoUrlRoute::ROUTE_NAME);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $route);

        $result = $this->seoUrlGenerator->generate($blogIds, $template, $route, Context::createDefaultContext(), $this->channelContext->getChannel());

        $expected = ['my-producT'];
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

        $criteria = (new Criteria())->setLimit($count);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }
}
