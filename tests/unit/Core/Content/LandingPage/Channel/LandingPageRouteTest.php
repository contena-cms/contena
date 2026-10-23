<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\LandingPage\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\LandingPage\Channel\LandingPageRouteResponse;
use Contena\Core\Content\LandingPage\Extension\LandingPageRouteExtension;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\LandingPage\LandingPageException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LandingPageRoute::class)]
class LandingPageRouteTest extends TestCase
{
    private MockObject&ChannelCmsPageLoaderInterface $cmsPageLoader;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->cmsPageLoader = $this->createMock(ChannelCmsPageLoaderInterface::class);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn(Uuid::randomHex());
        $channelContext->method('getLanguageId')->willReturn(Uuid::randomHex());
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $this->channelContext = $channelContext;
    }

    #[TestDox('The resolved cms page is loaded onto the landing page')]
    public function testLoadsLandingPageWithCmsPage(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: true);
        $cmsPage = new CmsPageEntity();
        $cmsPage->setId($landingPage->getCmsPageId() ?? '');

        $this->cmsPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->createCmsPageResult(new CmsPageCollection([$cmsPage])));

        $response = $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->channelContext);

        static::assertSame($landingPage, $response->getLandingPage());
        static::assertSame($cmsPage, $response->getLandingPage()->getCmsPage());
    }

    #[TestDox('A landing page without an assigned cms page is returned without loading one')]
    public function testLoadsLandingPageWithoutCmsPageId(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: false);

        $this->cmsPageLoader->expects($this->never())->method('load');

        $response = $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->channelContext);

        static::assertSame($landingPage, $response->getLandingPage());
        static::assertNull($response->getLandingPage()->getCmsPage());
    }

    #[TestDox('An unresolvable cms page fails the route')]
    public function testThrowsWhenCmsPageCannotBeLoaded(): void
    {
        $landingPage = $this->createLandingPage(withCmsPage: true);

        $this->cmsPageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn($this->createCmsPageResult(new CmsPageCollection()));

        $this->expectExceptionObject(LandingPageException::notFound($landingPage->getCmsPageId() ?? ''));

        $this->createRoute($landingPage)->load($landingPage->getId(), new Request(), $this->channelContext);
    }

    #[TestDox('An unknown landing page id fails the route')]
    public function testThrowsWhenLandingPageIsNotFound(): void
    {
        $missingId = Uuid::randomHex();

        $this->cmsPageLoader->expects($this->never())->method('load');

        /** @var StaticChannelRepository<LandingPageCollection> $repository */
        $repository = new StaticChannelRepository([new LandingPageCollection()]);

        $route = new LandingPageRoute(
            $repository,
            $this->cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder(static::createStub(Connection::class)),
            static::createStub(LandingPageDefinition::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectExceptionObject(LandingPageException::notFound($missingId));

        $route->load($missingId, new Request(), $this->channelContext);
    }

    public function testPublishesExtension(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request();
        $response = static::createStub(LandingPageRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('landing-page-route.load.pre', function (LandingPageRouteExtension $extension) use ($landingPageId, $request, $response): void {
            static::assertSame(['landingPageId' => $landingPageId, 'request' => $request, 'context' => $this->channelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $this->cmsPageLoader->expects($this->never())->method('load');

        $route = new LandingPageRoute(
            static::createStub(ChannelRepository::class),
            $this->cmsPageLoader,
            static::createStub(EntityCmsSlotConfigInheritanceBuilder::class),
            static::createStub(LandingPageDefinition::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($landingPageId, $request, $this->channelContext));
    }

    private function createRoute(LandingPageEntity $landingPage): LandingPageRoute
    {
        /** @var StaticChannelRepository<LandingPageCollection> $repository */
        $repository = new StaticChannelRepository([new LandingPageCollection([$landingPage])]);

        return new LandingPageRoute(
            $repository,
            $this->cmsPageLoader,
            new EntityCmsSlotConfigInheritanceBuilder(static::createStub(Connection::class)),
            static::createStub(LandingPageDefinition::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher())
        );
    }

    /**
     * @return EntitySearchResult<CmsPageCollection>
     */
    private function createCmsPageResult(CmsPageCollection $pages): EntitySearchResult
    {
        return new EntitySearchResult(
            'cms_page',
            $pages->count(),
            $pages,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createLandingPage(bool $withCmsPage): LandingPageEntity
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId(Uuid::randomHex());
        $landingPage->setUniqueIdentifier($landingPage->getId());
        if ($withCmsPage) {
            $landingPage->setCmsPageId(Uuid::randomHex());
        }

        return $landingPage;
    }
}
