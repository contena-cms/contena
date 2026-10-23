<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Frontend\Event\FrontendRenderEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FrontendRenderEvent::class)]
class FrontendRenderEventTest extends TestCase
{
    public function testParametersAreMergedWithTheDefaults(): void
    {
        $channelContext = Generator::generateChannelContext();

        $event = new FrontendRenderEvent(
            '@Frontend/frontend/page/content/index.html.twig',
            ['page' => 'content'],
            new Request(),
            $channelContext,
        );

        static::assertSame(
            [
                'context' => $channelContext,
                'headerParameters' => [],
                'footerParameters' => [],
                'page' => 'content',
            ],
            $event->getParameters()
        );
    }

    public function testPassedParametersOverrideTheDefaults(): void
    {
        $event = new FrontendRenderEvent(
            'index.html.twig',
            ['headerParameters' => ['navigationId' => 'foo']],
            new Request(),
            Generator::generateChannelContext(),
        );

        static::assertSame(['navigationId' => 'foo'], $event->getParameter('headerParameters'));
    }

    public function testGetParameterReturnsNullForUnknownKeys(): void
    {
        $event = new FrontendRenderEvent('index.html.twig', [], new Request(), Generator::generateChannelContext());

        static::assertNull($event->getParameter('unknown'));
    }

    public function testSetParameterAddsToTheParameters(): void
    {
        $event = new FrontendRenderEvent('index.html.twig', [], new Request(), Generator::generateChannelContext());

        $event->setParameter('page', 'content');

        static::assertSame('content', $event->getParameter('page'));
    }

    public function testGettersReturnTheConstructorArguments(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $event = new FrontendRenderEvent('index.html.twig', [], $request, $channelContext);

        static::assertSame('index.html.twig', $event->getView());
        static::assertSame($request, $event->getRequest());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($channelContext->getContext(), $event->getContext());
    }

    public function testSetChannelContextReplacesTheContext(): void
    {
        $event = new FrontendRenderEvent('index.html.twig', [], new Request(), Generator::generateChannelContext());

        $replacement = Generator::generateChannelContext();
        $event->setChannelContext($replacement);

        static::assertSame($replacement, $event->getChannelContext());
    }
}
