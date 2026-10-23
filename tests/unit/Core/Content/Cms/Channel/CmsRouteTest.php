<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Channel\ChannelCmsPageLoaderInterface;
use Contena\Core\Content\Cms\Channel\CmsRoute;
use Contena\Core\Content\Cms\Channel\CmsRouteResponse;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\Extension\CmsRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CmsRoute::class)]
class CmsRouteTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testGetDecorated(): void
    {
        $pageLoader = static::createStub(ChannelCmsPageLoaderInterface::class);
        $route = new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher()));

        $this->expectException(DecorationPatternException::class);
        $route->getDecorated();
    }

    public function testLoadHandlesSlotsAsArray(): void
    {
        $slots = [
            $this->ids->get('slot-1'),
            $this->ids->get('slot-2'),
            $this->ids->get('slot-3'),
        ];

        $request = new Request([
            'slots' => $slots,
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($slots);
        $context = Generator::generateChannelContext();

        $pageLoader = $this->createPageLoader($criteria, $searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadHandlesSlotsAsString(): void
    {
        $expectedSlots = [
            $this->ids->get('slot-1'),
            $this->ids->get('slot-2'),
            $this->ids->get('slot-3'),
        ];

        $request = new Request([
            'slots' => "{$this->ids->get('slot-1')}|{$this->ids->get('slot-2')}|{$this->ids->get('slot-3')}",
        ]);

        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = $this->getExpectedCriteria($expectedSlots);
        $context = Generator::generateChannelContext();

        $pageLoader = $this->createPageLoader($criteria, $searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadCmsPageWithoutProvidedSlots(): void
    {
        $request = new Request([]);
        $expectedCmsPage = new CmsPageEntity();

        $searchResult = $this->getSearchResult($expectedCmsPage);
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $context = Generator::generateChannelContext();

        $pageLoader = $this->createPageLoader($criteria, $searchResult);

        $actualCmsPage = (new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher())))->load($this->ids->get('cms-page'), $request, $context)->getCmsPage();
        static::assertSame($expectedCmsPage, $actualCmsPage);
    }

    public function testLoadThrowsExceptionIfNoPageFound(): void
    {
        $request = new Request([]);

        // empty search result
        $searchResult = $this->getSearchResult();

        $cmsPageId = $this->ids->get('cms-page');
        $criteria = new Criteria([$cmsPageId]);
        $context = Generator::generateChannelContext();

        $pageLoader = $this->createPageLoader($criteria, $searchResult);

        $route = new CmsRoute($pageLoader, new ExtensionDispatcher(new EventDispatcher()));

        $this->expectExceptionObject(CmsException::pageNotFound($cmsPageId));
        $route->load($cmsPageId, $request, $context);
    }

    public function testPublishesExtension(): void
    {
        $id = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateChannelContext();
        $response = static::createStub(CmsRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('cms-route.load.pre', static function (CmsRouteExtension $extension) use ($id, $request, $context, $response): void {
            static::assertSame(['id' => $id, 'request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CmsRoute(
            static::createStub(ChannelCmsPageLoaderInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($id, $request, $context));
    }

    /**
     * @param EntitySearchResult<CmsPageCollection> $searchResult
     */
    private function createPageLoader(Criteria $expectedCriteria, EntitySearchResult $searchResult): ChannelCmsPageLoaderInterface
    {
        $pageLoader = $this->createMock(ChannelCmsPageLoaderInterface::class);
        $pageLoader
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Criteria $criteria) use ($expectedCriteria, $searchResult): EntitySearchResult {
                static::assertEquals($expectedCriteria, $criteria);

                return $searchResult;
            });

        return $pageLoader;
    }

    /**
     * @param array<string> $slots
     */
    private function getExpectedCriteria(array $slots): Criteria
    {
        $criteria = new Criteria([$this->ids->get('cms-page')]);
        $criteria
            ->getAssociation('sections.blocks')
            ->addFilter(new EqualsAnyFilter('slots.id', $slots));

        return $criteria;
    }

    /**
     * @return EntitySearchResult<CmsPageCollection>
     */
    private function getSearchResult(?CmsPageEntity $cmsPage = null): EntitySearchResult
    {
        $collection = new CmsPageCollection();
        if ($cmsPage !== null) {
            $cmsPage->setUniqueIdentifier('cms-page');
            $collection->add($cmsPage);
        }

        $searchResult = static::createStub(EntitySearchResult::class);

        $searchResult
            ->method('getEntities')
            ->willReturn($collection);

        return $searchResult;
    }
}
