<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\Channel\ChannelApiSeoResolver;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelApiSeoResolver::class)]
class ChannelApiSeoResolverTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = ChannelApiSeoResolver::getSubscribedEvents();

        static::assertCount(1, $subscribedEvents);
        static::assertArrayHasKey(KernelEvents::RESPONSE, $subscribedEvents);
        static::assertSame('addSeoInformation', $subscribedEvents[KernelEvents::RESPONSE][0]);
        static::assertSame(11000, $subscribedEvents[KernelEvents::RESPONSE][1]);
    }

    public function testAddSeoInformation(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $blogEntity = $this->createBlogEntity();
        $response = new BlogListResponse(new EntitySearchResult(
            1,
            new BlogCollection([$blogEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($blogEntity->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blogEntity->getSeoUrls());
    }

    public function testAddSeoInformationWithExtensions(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $searchResult = new EntitySearchResult(
            0,
            new BlogCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $blog = $this->createBlogEntity();

        $result = new MockSeoUrlAwareExtension();
        $result->addSearchResult($blog);

        $searchResult->addExtension('multiSearchResult', $result);
        $response = new BlogListResponse($searchResult);

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );

        static::assertEmpty($blog->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blog->getSeoUrls());
    }

    public function testAddSeoInformationForSearchResultNestedInStructVars(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $blog = $this->createBlogEntity();
        $nestedResult = new EntitySearchResult(
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchResult = new EntitySearchResult(
            0,
            new BlogCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $searchResult->addExtension('cmsSlotData', new MockNestedSearchResultStruct($nestedResult));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse($searchResult)
        );

        static::assertEmpty($blog->getSeoUrls());

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNotEmpty($blog->getSeoUrls());
    }

    /**
     * @param callable(ChannelBlogEntity): list<RenderedElement> $forest
     */
    #[DataProvider('renderedElementPlacementProvider')]
    #[TestDox('adds SEO information for rendered element placed at $_dataName')]
    public function testAddSeoInformationForRenderedElement(callable $forest): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            Generator::generateChannelContext(),
        );

        $blog = $this->createBlogEntity();

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->createContentRouteResponse($forest($blog)),
        );

        $this->createChannelApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($blog));
    }

    /**
     * @return iterable<string, array{callable(ChannelBlogEntity): list<RenderedElement>}>
     */
    public static function renderedElementPlacementProvider(): iterable
    {
        yield 'directly under a property key' => [
            static fn (ChannelBlogEntity $blog): array => [
                new RenderedElement('element-1', 'blog-card', ['blog' => $blog]),
            ],
        ];

        yield 'inside a list-valued property' => [
            static fn (ChannelBlogEntity $blog): array => [
                new RenderedElement('element-1', 'blog-listing', ['blogs' => [$blog]]),
            ],
        ];

        yield 'two slot levels deep' => [
            static fn (ChannelBlogEntity $blog): array => [
                new RenderedElement('root', 'section', [], [
                    'content' => [
                        new RenderedElement('middle', 'grid', [], [
                            'inner' => [
                                new RenderedElement('leaf', 'blog-card', ['blog' => $blog]),
                            ],
                        ]),
                    ],
                ]),
            ],
        ];
    }

    #[TestDox('ignores non-Struct rendered property values')]
    public function testAddSeoInformationIgnoresNonStructRenderedPropertyValues(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            Generator::generateChannelContext(),
        );

        $blog = $this->createBlogEntity();
        $element = new RenderedElement('element-1', 'blog-card', [
            'headline' => 'A headline',
            'publishedAt' => new \DateTimeImmutable('2026-08-25 12:00:00'),
            'blog' => $blog,
        ]);

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $this->createContentRouteResponse([$element]),
        );

        $this->createChannelApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($blog));
    }

    #[TestDox('adds SEO information when rendered element is held directly in struct vars')]
    public function testAddSeoInformationForARenderedElementHeldDirectlyInStructVars(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            Generator::generateChannelContext(),
        );

        $blog = $this->createBlogEntity();
        $searchResult = new EntitySearchResult(
            0,
            new BlogCollection([]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
        $searchResult->addExtension('contentElement', new MockRenderedElementHolderStruct(
            new RenderedElement('element-1', 'blog-card', ['blog' => $blog]),
        ));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse($searchResult),
        );

        $this->createChannelApiSeoResolver()->addSeoInformation($event);

        static::assertSame(['random'], $this->collectedForeignKeys($blog));
    }

    #[DoesNotPerformAssertions]
    public function testResponseIsNotChannelApiResponse(): void
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);
    }

    public function testRequestHeaderDoesNotIncludeSeoUrls(): void
    {
        $blogEntity = $this->createBlogEntity();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse(new EntitySearchResult(
                1,
                new BlogCollection([$blogEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    public function testContextIsNoChannelContext(): void
    {
        $blogEntity = $this->createBlogEntity();

        $response = new BlogListResponse(new EntitySearchResult(
            1,
            new BlogCollection([$blogEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            Context::createDefaultContext(),
        );

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );

        $channelApiSeoResolver = $this->createChannelApiSeoResolver();
        $channelApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    /**
     * @return \Generator<string, array{bool, bool, list<string>, 3?: bool}>
     */
    public static function routeNameFilterCases(): \Generator
    {
        yield 'Web channels query only the Frontend route names' => [
            false,
            true,
            [TestBlogSeoUrlRoute::ROUTE_NAME],
        ];

        yield 'API channels query the Channel API route names and keep Frontend names as fallback' => [
            true,
            true,
            [BlogChannelApiUrlRoute::ROUTE_NAME, TestBlogSeoUrlRoute::ROUTE_NAME],
        ];

        yield 'API channels work without any Frontend routes registered' => [
            true,
            false,
            [BlogChannelApiUrlRoute::ROUTE_NAME],
        ];

        yield 'API channels fall back to Frontend names for entities without a Channel API route' => [
            true,
            true,
            [TestBlogSeoUrlRoute::ROUTE_NAME],
            false,
        ];
    }

    /**
     * @param list<string> $expectedRouteNames
     */
    #[DataProvider('routeNameFilterCases')]
    public function testRouteNameFilterMatchesChannelType(
        bool $headless,
        bool $withFrontendRoutes,
        array $expectedRouteNames,
        bool $withChannelApiRoutes = true,
    ): void {
        $context = $headless ? $this->createHeadlessChannelContext() : Generator::generateChannelContext();
        $blog = $this->createBlogEntity();
        $event = $this->createBlogListResponseEvent($blog, $context);
        $capturedCriteria = null;
        $resolver = $this->createChannelApiSeoResolver(
            seoUrlRouteRegistry: $withFrontendRoutes ? null : new SeoUrlRouteRegistry([]),
            withChannelApiRoutes: $withChannelApiRoutes,
            onSearch: static function (Criteria $criteria) use (&$capturedCriteria): void {
                $capturedCriteria = $criteria;
            },
        );

        $resolver->addSeoInformation($event);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame($expectedRouteNames, $this->getRouteNameFilterValues($capturedCriteria));
        static::assertNotEmpty($blog->getSeoUrls());
    }

    private function createBlogListResponseEvent(ChannelBlogEntity $blog, ChannelContext $context): ResponseEvent
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse(new EntitySearchResult(
                1,
                new BlogCollection([$blog]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );
    }

    /**
     * @return list<string>
     */
    private function collectedForeignKeys(ChannelBlogEntity $blog): array
    {
        $seoUrls = $blog->getSeoUrls();

        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return array_values($seoUrls->map(static fn (SeoUrlEntity $seoUrl): string => $seoUrl->getForeignKey()));
    }

    /**
     * @param list<RenderedElement> $forest
     */
    private function createContentRouteResponse(array $forest): ContentRouteResponse
    {
        return new ContentRouteResponse(new RenderResult(
            $forest,
            LayoutReference::create('layout-id', 'Layout', null),
            null,
        ));
    }

    private function createBlogEntity(string $identifier = 'random'): ChannelBlogEntity
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setUniqueIdentifier($identifier);

        return $blogEntity;
    }

    /**
     * @param array<string> $foreignKeys
     */
    private function createChannelApiSeoResolver(
        array $foreignKeys = ['random'],
        ?SeoUrlRouteRegistry $seoUrlRouteRegistry = null,
        ?\Closure $onSearch = null,
        bool $withChannelApiRoutes = true,
    ): ChannelApiSeoResolver {
        $definitionInstanceRegistry = $this->getDefinitionRegistry();

        $seoUrlCollection = new SeoUrlCollection();

        foreach ($foreignKeys as $foreignKey) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setUniqueIdentifier('seo-url.' . $foreignKey);
            $seoUrlEntity->setForeignKey($foreignKey);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $entitySearchResult = new EntitySearchResult(
            1,
            $seoUrlCollection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $blogDefinition = $definitionInstanceRegistry->getByClassOrEntityName('blog');

        // not a PHPUnit assertion to avoid indirect assertions and hiding risky tests, narrows from EntityDefinition
        \assert($blogDefinition instanceof BlogDefinition);

        $channelRepository = static::createStub(ChannelRepository::class);
        $channelRepository
            ->method('search')
            ->willReturnCallback(static function (Criteria $criteria) use ($entitySearchResult, $onSearch): EntitySearchResult {
                if ($onSearch !== null) {
                    $onSearch($criteria);
                }

                return $entitySearchResult;
            });

        return new ChannelApiSeoResolver(
            $channelRepository,
            $definitionInstanceRegistry,
            static::createStub(ChannelDefinitionInstanceRegistry::class),
            $seoUrlRouteRegistry ?? new SeoUrlRouteRegistry([new TestBlogSeoUrlRoute($blogDefinition)]),
            new EntityRouteResolver(
                new SeoUrlRouteRegistry([]),
                static::createStub(SeoUrlPlaceholderHandlerInterface::class),
                static::createStub(RouterInterface::class),
                $withChannelApiRoutes ? [new BlogChannelApiUrlRoute($blogDefinition)] : [],
            ),
        );
    }

    private function createHeadlessChannelContext(): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setTypeId(Defaults::CHANNEL_TYPE_API);
        $channel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        return Generator::generateChannelContext(channel: $channel);
    }

    /**
     * @return list<string>
     */
    private function getRouteNameFilterValues(Criteria $criteria): array
    {
        foreach ($criteria->getFilters() as $filter) {
            if ($filter instanceof EqualsAnyFilter && $filter->getField() === 'routeName') {
                return array_values(array_map('strval', $filter->getValue()));
            }
        }

        return [];
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                ChannelBlogDefinition::class,
                SeoUrlDefinition::class,
                BlogTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
