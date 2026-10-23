<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Extension\ContextRouteExtension;
use Contena\Core\System\Channel\Channel\ContextLoadRouteResponse;
use Contena\Core\System\Channel\Channel\ContextRoute;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ContextRoute::class)]
class ContextRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ContextRoute::class));

        (new ContextRoute(new ExtensionDispatcher(new EventDispatcher())))->getDecorated();
    }

    public function testLoadReturnsContextTokenHeader(): void
    {
        $context = Generator::generateChannelContext(token: 'test-token');

        $response = (new ContextRoute(new ExtensionDispatcher(new EventDispatcher())))->load($context);

        static::assertSame($context, $response->getContext());
        static::assertSame('test-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testPublishesExtension(): void
    {
        $context = Generator::generateChannelContext();
        $response = new ContextLoadRouteResponse($context);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('context-route.load.pre', static function (ContextRouteExtension $extension) use ($context, $response): void {
            static::assertSame(['context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContextRoute(new ExtensionDispatcher($dispatcher));

        static::assertSame($response, $route->load($context));
    }
}
