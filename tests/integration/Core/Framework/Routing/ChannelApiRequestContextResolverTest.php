<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRequestContextResolver;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ChannelApiRequestContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testResolveChannelApiSource(): void
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);

        static::getContainer()->get(ApiRequestContextResolver::class)->resolve($request);

        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
        static::assertInstanceOf(Context::class, $context);
        static::assertInstanceOf(ChannelApiSource::class, $context->getSource());
        static::assertSame(TestDefaults::CHANNEL, $context->getSource()->getChannelId());
    }
}
