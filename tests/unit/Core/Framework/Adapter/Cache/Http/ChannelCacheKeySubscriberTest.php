<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Contena\Core\Framework\Adapter\Cache\Http\ChannelCacheKeySubscriber;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ChannelCacheKeySubscriber::class)]
class ChannelCacheKeySubscriberTest extends TestCase
{
    public function testAddsTheAuthenticatedChannelToTheCacheKey(): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('channel');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(`id`)) FROM `channel` WHERE `access_key` = :accessKey', [
                'accessKey' => $accessKey,
            ])
            ->willReturn('019fff00000000000000000000000000');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $accessKey);

        $event = new HttpCacheKeyEvent($request);

        new ChannelCacheKeySubscriber($connection)->addChannel($event);

        static::assertTrue($event->has('channel'));
        static::assertSame('019fff00000000000000000000000000', $event->get('channel'));
    }

    public function testIgnoresRequestsWithoutAnAccessKey(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $event = new HttpCacheKeyEvent(new Request());

        new ChannelCacheKeySubscriber($connection)->addChannel($event);

        static::assertFalse($event->has('channel'));
    }

    public function testIgnoresMalformedAccessKeys(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_ACCESS_KEY, 'not-an-access-key');

        $event = new HttpCacheKeyEvent($request);

        new ChannelCacheKeySubscriber($connection)->addChannel($event);

        static::assertFalse($event->has('channel'));
    }
}
