<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Frontend\Framework\Routing\CanonicalLinkListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CanonicalLinkListener::class)]
class CanonicalLinkListenerTest extends TestCase
{
    public function testErrorResponseDoesNothing(): void
    {
        $response = new Response(null, Response::HTTP_TEMPORARY_REDIRECT);

        (new CanonicalLinkListener())(new BeforeSendResponseEvent(new Request(), $response));

        static::assertCount(2, $response->headers->all());
    }

    public function testLinkHeaderGetsAdded(): void
    {
        $response = new Response();
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_CANONICAL_LINK, 'foo');

        (new CanonicalLinkListener())(new BeforeSendResponseEvent($request, $response));

        static::assertCount(3, $response->headers->all());
        static::assertSame('<foo>; rel="canonical"', $response->headers->get('Link'));
    }
}
