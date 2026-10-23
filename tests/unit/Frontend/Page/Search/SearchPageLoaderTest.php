<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\Search\SearchPageLoadedEvent;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SearchPageLoader::class)]
class SearchPageLoaderTest extends TestCase
{
    public function testItLoad(): void
    {
        $request = new Request(['search' => 'test']);
        $channelContext = $this->getChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($channelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($channelContext, $event->getChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(AbstractBlogSearchRoute::class),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $channelContext);

        static::assertSame('test', $page->getSearchTerm());
    }

    public function testItLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $channelContext = $this->getChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($channelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($channelContext, $event->getChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(AbstractBlogSearchRoute::class),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $channelContext);

        static::assertSame('', $page->getSearchTerm());
    }

    private function getChannelContext(): ChannelContext
    {
        $channelEntity = new ChannelEntity();
        $channelEntity->setId('channelId');

        return Generator::generateChannelContext(
            channel: $channelEntity,
        );
    }
}
