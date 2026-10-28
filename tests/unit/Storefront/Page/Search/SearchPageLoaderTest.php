<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Storefront\Page\GenericPageLoader;
use Contena\Storefront\Page\Search\SearchPageLoadedEvent;
use Contena\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SearchPageLoader::class)]
class SearchPageLoaderTest extends TestCase
{
    public function testItLoad(): void
    {
        $request = new Request(['search' => 'test']);
        $salesChannelContext = $this->getSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($salesChannelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(AbstractProductSearchRoute::class),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $salesChannelContext);

        static::assertSame('test', $page->getSearchTerm());
    }

    public function testItLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $salesChannelContext = $this->getSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($salesChannelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($salesChannelContext, $event->getSalesChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(AbstractProductSearchRoute::class),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $salesChannelContext);

        static::assertSame('', $page->getSearchTerm());
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }
}
