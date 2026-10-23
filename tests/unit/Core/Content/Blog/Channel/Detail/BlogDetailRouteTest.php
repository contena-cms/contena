<?php
declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Detail;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvokedCount;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\Extension\BlogDetailRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\Detail\Event\ResolveVariantIdEvent;
use Contena\Core\Content\Blog\Channel\Detail\BlogConfiguratorLoader;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRouteResponse;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogDetailRoute::class)]
class BlogDetailRouteTest extends TestCase
{
    /**
     * @var Stub&ChannelRepository<ChannelBlogCollection>
     */
    private ChannelRepository $blogRepository;

    /**
     * @var Stub&SystemConfigService
     */
    private SystemConfigService $systemConfig;

    /**
     * @var Stub&EntityRepository<BlogTranslationCollection>
     */
    private Stub&EntityRepository $blogTranslationRepository;

    private Connection&Stub $connection;

    private BlogDetailRoute $route;

    private ChannelContext $context;

    private IdsCollection $idsCollection;

    private CategoryBreadcrumbBuilder&Stub $breadcrumbBuilder;

    private ChannelCmsPageLoader&Stub $cmsPageLoader;

    private AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = Generator::generateChannelContext();
        $this->idsCollection = new IdsCollection();
        $this->blogRepository = static::createStub(ChannelRepository::class);
        $this->blogTranslationRepository = static::createStub(EntityRepository::class);
        $this->systemConfig = static::createStub(SystemConfigService::class);
        $this->connection = static::createStub(Connection::class);
        $this->breadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $this->cmsPageLoader = static::createStub(ChannelCmsPageLoader::class);
        $this->blogCloseoutFilterFactory = new BlogCloseoutFilterFactory();
        $this->eventDispatcher = new EventDispatcher();

        $this->route = $this->buildRoute();
    }

    public function testLoadMainVariant(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId(Uuid::randomHex());
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('mainVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->exactly(1))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );
        $route = $this->buildRoute($blogRepository);

        $result = $route->load('1', new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
    }

    public function testLoadBestVariant(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setCmsPageId('4');
        $blogEntity->setId($this->idsCollection->create('blog1'));
        $blogEntity->setAvailable(true);
        $blogEntity->setUniqueIdentifier('BestVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));

        $blog1Id = $this->idsCollection->create('blog1');
        $idsSearchResult = new IdSearchResult(
            1,
            [
                $blog1Id => [
                    'primaryKey' => $blog1Id,
                    'data' => [],
                ],
            ],
            new Criteria(),
            $this->context->getContext()
        );
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('blog', 4, new BlogCollection([$blogEntity]), null, new Criteria(), $this->context->getContext())
            );
        $route = $this->buildRoute($blogRepository);

        $result = $route->load($blog1Id, new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('BestVariant', $result->getBlog()->getUniqueIdentifier());
        static::assertTrue($result->getBlog()->getAvailable());
    }

    public function testLoadParentSearchUsesMatchedVariantWhenFindBestVariantEnabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => null,
                'parentId' => null,
            ]);

        $this->systemConfig->method('getBool')
            ->willReturnCallback(static fn (string $key, ?string $_channelId = null): bool => $key === 'core.listing.findBestVariant');

        $blogTerm = new ChannelBlogEntity();
        $blogTerm->setCmsPageId('term');
        $blogTerm->setId($this->idsCollection->create('term'));
        $blogTerm->setUniqueIdentifier('term');
        $blogTerm->setName('term');
        $blogTerm->internalSetEntityData('blog', new FieldVisibility([]));

        $blog1Id = $this->idsCollection->create('blog1');
        $idsSearchResult = new IdSearchResult(
            1,
            [
                $blog1Id => [
                    'primaryKey' => $blog1Id,
                    'data' => [],
                ],
            ],
            new Criteria(),
            $this->context->getContext()
        );
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($blog1Id): void {
            static::assertSame($blog1Id, $event->getResolvedVariantId());
        });

        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('blog', 4, new BlogCollection([$blogTerm]), null, new Criteria(), $this->context->getContext())
            );
        $request = new Request();
        $request->query->set('search', 'term');

        $route = $this->buildRoute($blogRepository, $connection);
        $result = $route->load($blog1Id, $request, $this->context, new Criteria());

        static::assertSame('term', $result->getBlog()->getCmsPageId());
        static::assertSame('term', $result->getBlog()->getUniqueIdentifier());
    }

    public function testLoadParentSearchKeepsMainVariantWhenFindBestVariantDisabled(): void
    {
        $mainVariantId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "' . $mainVariantId . '"}',
                'parentId' => null,
            ]);

        $this->systemConfig->method('getBool')->willReturn(false);

        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($mainVariantId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('mainVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($mainVariantId): void {
            static::assertSame($mainVariantId, $event->getResolvedVariantId());
        });

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->never())
            ->method('searchIds');
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $request = new Request();
        $request->query->set('search', 'term');

        $result = $this->buildRoute($blogRepository, $connection)->load(Uuid::randomHex(), $request, $this->context, new Criteria());

        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
    }

    public function testLoadVariantListingConfig(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $blogId = Uuid::randomHex();
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($blogId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('2');
        $blogEntity->setAvailable(true);
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($blogId): void {
            static::assertSame($blogId, $event->getBlogId());
            static::assertSame('2', $event->getResolvedVariantId());
        });

        $result = $this->buildRoute($blogRepository, $connection)->load($blogId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getBlog()->getUniqueIdentifier());
        static::assertTrue($result->getBlog()->getAvailable());
    }

    public function testLoadFallsBackToBestVariantWhenConfiguredMainVariantIsNotAvailable(): void
    {
        $blogId = Uuid::randomHex();
        $mainVariantId = Uuid::randomHex();
        $bestVariantId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": false, "mainVariantId": "' . $mainVariantId . '"}',
                'parentId' => null,
            ]);

        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($bestVariantId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('best-variant');
        $blogEntity->setAvailable(true);
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(
                1,
                [
                    $bestVariantId => [
                        'primaryKey' => $bestVariantId,
                        'data' => [],
                    ],
                ],
                new Criteria(),
                $this->context->getContext()
            ));
        $searchCall = 0;
        $blogRepository->expects($this->exactly(2))
            ->method('search')
            ->with(static::callback(function (Criteria $criteria) use (&$searchCall, $mainVariantId, $bestVariantId): bool {
                ++$searchCall;
                static::assertSame($searchCall === 1 ? [$mainVariantId] : [$bestVariantId], $criteria->getIds());

                return true;
            }))
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    'blog',
                    0,
                    new BlogCollection(),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                ),
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $result = $this->buildRoute($blogRepository, $connection)->load($blogId, new Request(), $this->context, new Criteria());

        static::assertSame('best-variant', $result->getBlog()->getUniqueIdentifier());
    }

    public function testResolveVariantIdFromEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": true, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $variantId = Uuid::randomHex();
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($variantId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setAvailable(true);
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (Criteria $criteria) use ($variantId): bool {
                $ids = $criteria->getIds();
                static::assertCount(1, $ids);
                static::assertSame($variantId, reset($ids));

                return true;
            }))
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($variantId): void {
            $event->setResolvedVariantId($variantId);
        });

        $result = $this->buildRoute($blogRepository, $connection)->load(Uuid::randomHex(), new Request(), $this->context, new Criteria());

        static::assertSame($variantId, $result->getBlog()->getUniqueIdentifier());
        static::assertTrue($result->getBlog()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithWrongTypeForDisplayParent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": 1, "mainVariantId": null}', // Wrong displayParent type, should be boolean
                'parentId' => '2',
            ]);

        $blogId = Uuid::randomHex();
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($blogId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('2');
        $blogEntity->setAvailable(true);
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($blogId): void {
            static::assertSame($blogId, $event->getBlogId());
            // In checkVariantListingConfig we want to make sure that the variant ID is not returned against displayParent when no variant ID is set
            static::assertNull($event->getResolvedVariantId(), 'Wrong variant ID resolved:' . $event->getResolvedVariantId());
        });

        $result = $this->buildRoute($blogRepository, $connection)->load($blogId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getBlog()->getUniqueIdentifier());
        static::assertTrue($result->getBlog()->getAvailable());
    }

    public function testResolveVariantIdFromEventWithDisplayParent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'variantListingConfig' => '{"displayParent": 1, "mainVariantId": "2"}',
                'parentId' => '2',
            ]);

        $blogId = Uuid::randomHex();
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId($blogId);
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('2');
        $blogEntity->setAvailable(true);
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($blogId): void {
            static::assertSame($blogId, $event->getBlogId());
            // In checkVariantListingConfig we want to make sure that the variant ID is returned even if displayParent is true
            static::assertSame('2', $event->getResolvedVariantId(), 'Wrong variant ID resolved:' . $event->getResolvedVariantId());
        });

        $result = $this->buildRoute($blogRepository, $connection)->load($blogId, new Request(), $this->context, new Criteria());

        static::assertSame('2', $result->getBlog()->getUniqueIdentifier());
        static::assertTrue($result->getBlog()->getAvailable());
    }

    public function testConfigHideCloseoutBlogsWhenOutOfStockFiltersResults(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId(Uuid::randomHex());
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('BestVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));

        $criteria2 = new Criteria([$this->idsCollection->get('blog2')]);
        $criteria2->setTitle('blog-detail-route');
        $criteria2->addFilter(
            new BlogAvailableFilter('', BlogVisibilityDefinition::VISIBILITY_LINK)
        );

        $filter = $this->blogCloseoutFilterFactory->create($this->context);
        $filter->addQuery(new EqualsFilter('blog.parentId', null));
        $criteria2->addFilter($filter);

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult('blog', 4, new BlogCollection([$blogEntity]), null, new Criteria(), $this->context->getContext())
            );

        $this->systemConfig->method('getBool')->willReturn(true);

        $result = $this->buildRoute($blogRepository)->load($this->idsCollection->get('blog2'), new Request(), $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('BestVariant', $result->getBlog()->getUniqueIdentifier());
    }

    public function testSkipConfiguratorQueryParameterExcludingConfigurator(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId(Uuid::randomHex());
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('mainVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $route = $this->buildRoute($blogRepository);
        $request = new Request();

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
        static::assertNotNull($result->getConfigurator());

        $request->query->set('skipConfigurator', true);

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
        static::assertNull($result->getConfigurator());
    }

    public function testSkipCmsPageQueryParameterExcludingCmsPage(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId(Uuid::randomHex());
        $blogEntity->setCmsPageId('4');
        $blogEntity->setUniqueIdentifier('mainVariant');
        $blogEntity->internalSetEntityData('blog', new FieldVisibility([]));

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blogEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $cmsPage = new CmsPageEntity();
        $cmsPage->setId('4');

        $cmsPageLoader = $this->createMock(ChannelCmsPageLoader::class);
        $cmsPageLoader->expects($this->once())
            ->method('load')
            ->willReturn(new EntitySearchResult(
                'cms_page',
                1,
                new CmsPageCollection([$cmsPage]),
                null,
                new Criteria(),
                $this->context->getContext()
            ));

        $route = $this->buildRoute($blogRepository, cmsPageLoader: $cmsPageLoader);

        // Reset cmsPage of blog
        $blogEntity->assign(['cmsPage' => null]);

        $request = new Request();

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
        static::assertSame($cmsPage, $result->getBlog()->getCmsPage());

        // Reset cmsPage of blog
        $blogEntity->assign(['cmsPage' => null]);

        $request->query->set('skipCmsPage', true);

        $result = $route->load('1', $request, $this->context, new Criteria());

        static::assertSame('4', $result->getBlog()->getCmsPageId());
        static::assertSame('mainVariant', $result->getBlog()->getUniqueIdentifier());
        static::assertNull($result->getBlog()->getCmsPage());
    }

    public function testLoadBlogNotFound(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(BlogNotFoundException::class);
        } else {
            $this->expectException(BlogException::class);
        }

        $this->route->load('1', new Request(), $this->context, new Criteria());
    }

    public function testLoadParentWithoutAvailableVariantThrowsNotFound(): void
    {
        $parentId = $this->idsCollection->create('parent');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'variantListingConfig' => null,
            'parentId' => null,
        ]);

        $parent = new ChannelBlogEntity();
        $parent->setId($parentId);
        $parent->setChildCount(2);
        $parent->internalSetEntityData('blog', new FieldVisibility([]));

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn(new IdSearchResult(0, [], new Criteria(), $this->context->getContext()));
        $blogRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $criteria): bool => $criteria->getIds() === [$parentId]))
            ->willReturn(new EntitySearchResult('blog', 1, new BlogCollection([$parent]), null, new Criteria(), $this->context->getContext()));

        $this->expectExceptionObject(BlogException::blogNotFound($parentId));

        $this->buildRoute($blogRepository, $connection)->load($parentId, new Request(), $this->context, new Criteria());
    }

    public function testLoadParentResolvedByEventIsNotRejected(): void
    {
        $parentId = $this->idsCollection->create('parent');

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn([
            'variantListingConfig' => null,
            'parentId' => null,
        ]);

        $this->eventDispatcher->addListener(ResolveVariantIdEvent::class, static function (ResolveVariantIdEvent $event) use ($parentId): void {
            $event->setResolvedVariantId($parentId);
        });

        $parent = new ChannelBlogEntity();
        $parent->setId($parentId);
        $parent->setChildCount(2);
        $parent->internalSetEntityData('blog', new FieldVisibility([]));

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->never())->method('searchIds');
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('blog', 1, new BlogCollection([$parent]), null, new Criteria(), $this->context->getContext()));

        $result = $this->buildRoute($blogRepository, $connection)->load($parentId, new Request(), $this->context, new Criteria());

        static::assertSame($parentId, $result->getBlog()->getId());
    }

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = new BlogDetailRouteResponse(new ChannelBlogEntity(), null);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-detail-route.load.pre', function (BlogDetailRouteExtension $extension) use ($blogId, $request, $criteria, $response): void {
            static::assertSame(['blogId' => $blogId, 'request' => $request, 'context' => $this->context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogDetailRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(Connection::class),
            static::createStub(BlogConfiguratorLoader::class),
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(ChannelCmsPageLoader::class),
            static::createStub(EntityCmsSlotConfigInheritanceBuilder::class),
            static::createStub(ChannelBlogDefinition::class),
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($blogId, $request, $this->context, $criteria));
    }

    #[DataProvider('breadcrumbCategoryDataProvider')]
    public function testLoadBreadcrumbCategory(
        ChannelBlogEntity $blog,
        bool $buildBreadcrumbByReferrerCategory,
        ?string $referrerCategoryId,
        InvokedCount $getBlogCategoryByReferrerCount,
        InvokedCount $getBlogSeoCategoryCount,
        ?CategoryEntity $breadcrumbCategory
    ): void {
        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->exactly(1))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$blog]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );
        $this->systemConfig->method('getBool')->willReturn($buildBreadcrumbByReferrerCategory);
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($getBlogCategoryByReferrerCount)
            ->method('getBlogCategoryByReferrer')
            ->willReturn($breadcrumbCategory);
        $breadcrumbBuilder->expects($getBlogSeoCategoryCount)
            ->method('getBlogSeoCategory')
            ->willReturn($breadcrumbCategory);

        $request = new Request();

        if ($referrerCategoryId) {
            $request->query->set('referrerCategoryId', $referrerCategoryId);
        }

        $result = $this->buildRoute($blogRepository, breadcrumbBuilder: $breadcrumbBuilder)->load('1', $request, $this->context, new Criteria());

        static::assertSame($breadcrumbCategory, $result->getBlog()->getSeoCategory());
    }

    public static function breadcrumbCategoryDataProvider(): \Generator
    {
        $defaultBreadcrumbCategory = new CategoryEntity();
        $defaultBreadcrumbCategory->setId(Uuid::randomHex());
        $parentCategory = new CategoryEntity();
        $parentCategory->setId(Uuid::randomHex());
        $secondCategory = new CategoryEntity();
        $secondCategory->setId(Uuid::randomHex());
        $thirdCategory = new CategoryEntity();
        $thirdCategory->setId(Uuid::randomHex());

        $blog = new ChannelBlogEntity();
        $blog->setId(Uuid::randomHex());
        $blog->setCategoryIds([$defaultBreadcrumbCategory->getId(), $secondCategory->getId()]);
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blogWithoutCategories = new ChannelBlogEntity();
        $blogWithoutCategories->setId(Uuid::randomHex());
        $blogWithoutCategories->internalSetEntityData('blog', new FieldVisibility([]));

        yield 'Load default breadcrumb category with disabled referrer feature' => [
            $blog,
            false,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load no breadcrumb category when blog has no categories assigned' => [
            $blogWithoutCategories,
            false,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            null,
        ];

        yield 'Load default breadcrumb category with enabled referrer feature and no referrerCategoryId' => [
            $blog,
            true,
            null,
            new InvokedCount(0),
            new InvokedCount(1),
            $defaultBreadcrumbCategory,
        ];

        yield 'Load breadcrumb category by referrerCategoryId with enabled referrer feature' => [
            $blog,
            true,
            $secondCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $secondCategory,
        ];

        yield 'Load breadcrumb category by referrerCategoryId with enabled referrer feature and referrerCategoryId being a parent of a category assigned to the blog' => [
            $blog,
            true,
            $parentCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $parentCategory,
        ];

        yield 'Load default breadcrumb category with enabled referrer feature and unassigned referrerCategoryId' => [
            $blog,
            true,
            $thirdCategory->getId(),
            new InvokedCount(1),
            new InvokedCount(0),
            $defaultBreadcrumbCategory,
        ];
    }

    /**
     * @param (ChannelRepository<ChannelBlogCollection>&MockObject)|null $blogRepository
     */
    private function buildRoute(
        ?ChannelRepository $blogRepository = null,
        ?Connection $connection = null,
        ?CategoryBreadcrumbBuilder $breadcrumbBuilder = null,
        ?ChannelCmsPageLoader $cmsPageLoader = null,
    ): BlogDetailRoute {
        return new BlogDetailRoute(
            $blogRepository ?? $this->blogRepository,
            $this->blogTranslationRepository,
            $this->systemConfig,
            $connection ?? $this->connection,
            static::createStub(BlogConfiguratorLoader::class),
            $breadcrumbBuilder ?? $this->breadcrumbBuilder,
            $cmsPageLoader ?? $this->cmsPageLoader,
            static::createStub(EntityCmsSlotConfigInheritanceBuilder::class),
            new ChannelBlogDefinition(),
            $this->blogCloseoutFilterFactory,
            $this->eventDispatcher,
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
