<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\Channel\StoreApiSeoResolver;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Test\TestBlogSeoUrlRoute;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
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
#[CoversClass(StoreApiSeoResolver::class)]
class StoreApiSeoResolverTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $subscribedEvents = StoreApiSeoResolver::getSubscribedEvents();

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
            'blog',
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

        static::assertCount(0, $blogEntity->getSeoUrls() ?? []);

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotCount(0, $blogEntity->getSeoUrls() ?? []);
    }

    public function testAddSeoWithRepeatedEntity(): void
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT,
            static::createStub(ChannelContext::class),
        );

        $blogEntity = $this->createBlogEntity();

        // two blogs with same unique identifier
        $crossSellingBlog1 = $this->createBlogEntity('cross-selling-blog');
        $crossSellingBlog2 = $this->createBlogEntity('cross-selling-blog');

        $crossSellingBlogEntity1 = new BlogCrossSellingEntity();
        $crossSellingBlogEntity1->setUniqueIdentifier('cross-item-1');
        $crossSellingBlogEntity1->setBlog($crossSellingBlog1);

        $crossSellingBlogEntity2 = new BlogCrossSellingEntity();
        $crossSellingBlogEntity2->setUniqueIdentifier('cross-item-2');
        $crossSellingBlogEntity2->setBlog($crossSellingBlog2);

        $blogCrossSellingCollection = new BlogCrossSellingCollection([$crossSellingBlogEntity1, $crossSellingBlogEntity2]);

        $blogEntity->setCrossSellings($blogCrossSellingCollection);

        $response = new BlogListResponse(new EntitySearchResult(
            'blog',
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

        static::assertCount(0, $blogEntity->getSeoUrls() ?? []);

        $storeApiSeoResolver = $this->createStoreApiSeoResolver(['random', 'cross-selling-blog']);
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotCount(0, $blogEntity->getSeoUrls() ?? []);

        $crossSellingBlog1 = $blogEntity->getCrossSellings()?->get('cross-item-1');

        static::assertInstanceOf(BlogCrossSellingEntity::class, $crossSellingBlog1);
        static::assertInstanceOf(ChannelBlogEntity::class, $outputBlog1 = $crossSellingBlog1->getBlog());
        static::assertSame('cross-selling-blog', $outputBlog1->getUniqueIdentifier());
        static::assertNotNull($outputBlog1->getSeoUrls());

        $crossSellingBlog2 = $blogEntity->getCrossSellings()?->get('cross-item-2');

        static::assertInstanceOf(BlogCrossSellingEntity::class, $crossSellingBlog2);
        static::assertInstanceOf(ChannelBlogEntity::class, $outputBlog2 = $crossSellingBlog2->getBlog());
        static::assertSame('cross-selling-blog', $outputBlog2->getUniqueIdentifier());
        static::assertNotNull($outputBlog2->getSeoUrls());
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
            'blog',
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

        static::assertCount(0, $blog->getSeoUrls() ?? []);

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotCount(0, $blog->getSeoUrls() ?? []);
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
            'blog',
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );

        $searchResult = new EntitySearchResult(
            'blog',
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

        static::assertCount(0, $blog->getSeoUrls() ?? []);

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNotCount(0, $blog->getSeoUrls() ?? []);
    }

    #[DoesNotPerformAssertions]
    public function testResponseIsNotStoreApiResponse(): void
    {
        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);
    }

    #[DataProvider('missingSeoHeaderCases')]
    public function testRequestHeaderDoesNotIncludeSeoUrls(?string $headerValue): void
    {
        $blogEntity = $this->createBlogEntity();
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, static::createStub(ChannelContext::class));
        if ($headerValue !== null) {
            $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, $headerValue);
        }

        $event = new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new BlogListResponse(new EntitySearchResult(
                'blog',
                1,
                new BlogCollection([$blogEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            )),
        );

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    public static function missingSeoHeaderCases(): \Generator
    {
        yield 'header absent' => [null];
        yield 'header empty' => [''];
    }

    public function testContextIsNoChannelContext(): void
    {
        $blogEntity = $this->createBlogEntity();

        $response = new BlogListResponse(new EntitySearchResult(
            'blog',
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

        $storeApiSeoResolver = $this->createStoreApiSeoResolver();
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertNull($blogEntity->getSeoUrls());
    }

    /**
     * @return \Generator<string, array{bool, bool, list<string>, 3?: bool}>
     */
    public static function routeNameFilterCases(): \Generator
    {
        yield 'frontend sales channels query only the frontend route names' => [
            false,
            true,
            [TestBlogSeoUrlRoute::ROUTE_NAME],
        ];

        yield 'headless sales channels query the store-api route names, frontend names stay as fallback' => [
            true,
            true,
            [BlogStoreApiUrlRoute::ROUTE_NAME, TestBlogSeoUrlRoute::ROUTE_NAME],
        ];

        yield 'headless sales channels work without any frontend routes registered' => [
            true,
            false,
            [BlogStoreApiUrlRoute::ROUTE_NAME],
        ];

        yield 'headless sales channels fall back to the frontend names for entities without a store-api route' => [
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
    public function testRouteNameFilterMatchesChannelType(bool $headless, bool $withFrontendRoutes, array $expectedRouteNames, bool $withStoreApiRoutes = true): void
    {
        $context = $headless ? $this->createHeadlessChannelContext() : Generator::generateChannelContext();

        $blogEntity = $this->createBlogEntity();
        $event = $this->createBlogListResponseEvent($blogEntity, $context);

        $capturedCriteria = null;
        $storeApiSeoResolver = $this->createStoreApiSeoResolver(
            seoUrlRouteRegistry: $withFrontendRoutes ? null : new SeoUrlRouteRegistry([]),
            withStoreApiRoutes: $withStoreApiRoutes,
            onSearch: static function (Criteria $criteria) use (&$capturedCriteria): void {
                $capturedCriteria = $criteria;
            },
        );
        $storeApiSeoResolver->addSeoInformation($event);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame($expectedRouteNames, $this->getRouteNameFilterValues($capturedCriteria));
        static::assertNotCount(0, $blogEntity->getSeoUrls() ?? []);
    }

    private function createBlogListResponseEvent(ChannelBlogEntity $blogEntity, ChannelContext $context): ResponseEvent
    {
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEO_URLS, 'true');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        $response = new BlogListResponse(new EntitySearchResult(
            'blog',
            1,
            new BlogCollection([$blogEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }

    private function createBlogEntity(string $identifier = 'random'): ChannelBlogEntity
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogEntity->setUniqueIdentifier($identifier);

        return $blogEntity;
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

    /**
     * @param array<string> $foreignKeys
     */
    private function createStoreApiSeoResolver(
        array $foreignKeys = ['random'],
        ?SeoUrlRouteRegistry $seoUrlRouteRegistry = null,
        ?\Closure $onSearch = null,
        bool $withStoreApiRoutes = true,
    ): StoreApiSeoResolver {
        $definitionInstanceRegistry = $this->getDefinitionRegistry();

        $seoUrlCollection = new SeoUrlCollection();

        foreach ($foreignKeys as $foreignKey) {
            $seoUrlEntity = new SeoUrlEntity();
            $seoUrlEntity->setUniqueIdentifier('seo-url.' . $foreignKey);
            $seoUrlEntity->setForeignKey($foreignKey);

            $seoUrlCollection->add($seoUrlEntity);
        }

        $entitySearchResult = new EntitySearchResult(
            'seo_url',
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

        return new StoreApiSeoResolver(
            $channelRepository,
            $definitionInstanceRegistry,
            static::createStub(ChannelDefinitionInstanceRegistry::class),
            $seoUrlRouteRegistry ?? new SeoUrlRouteRegistry([new TestBlogSeoUrlRoute($blogDefinition)]),
            new EntityRouteResolver(
                new SeoUrlRouteRegistry([]),
                static::createStub(SeoUrlPlaceholderHandlerInterface::class),
                static::createStub(RouterInterface::class),
                $withStoreApiRoutes ? [new BlogStoreApiUrlRoute($blogDefinition)] : [],
            ),
        );
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                ChannelBlogDefinition::class,
                SeoUrlDefinition::class,
                BlogCrossSellingDefinition::class,
                BlogTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }
}
