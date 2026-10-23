<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\Cms\Events\CmsPageLoadedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CmsPageLoadedEvent::class)]
class CmsPageLoadedEventTest extends TestCase
{
    public function testAcceptsACmsPageCollection(): void
    {
        $result = new CmsPageCollection();

        $event = new CmsPageLoadedEvent(new Request(), $result, static::createStub(ChannelContext::class));

        static::assertSame($result, $event->getResult());
    }

    public function testRejectsAPlainEntityCollection(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing a plain EntityCollection as $result is deprecated, pass a CmsPageCollection instead.'
        ));
        // @phpstan-ignore argument.type (the deprecated wide parameter type is exactly what this test exercises)
        new CmsPageLoadedEvent(new Request(), new EntityCollection(), static::createStub(ChannelContext::class));
    }

    public function testExposesRequestAndChannelContext(): void
    {
        $context = Context::createDefaultContext();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn($context);
        $request = new Request();

        $event = new CmsPageLoadedEvent($request, new CmsPageCollection(), $channelContext);

        static::assertSame($request, $event->getRequest());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($context, $event->getContext());
    }
}
