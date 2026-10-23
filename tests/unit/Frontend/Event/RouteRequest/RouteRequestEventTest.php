<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Event\RouteRequest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Test\Generator;
use Contena\Frontend\Event\RouteRequest\LanguageRouteRequestEvent;
use Contena\Frontend\Event\RouteRequest\RouteRequestEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RouteRequestEvent::class)]
class RouteRequestEventTest extends TestCase
{
    public function testGettersReturnTheConstructorArguments(): void
    {
        $frontendRequest = new Request();
        $storeApiRequest = new Request();
        $channelContext = Generator::generateChannelContext();
        $criteria = new Criteria();

        $event = new LanguageRouteRequestEvent($frontendRequest, $storeApiRequest, $channelContext, $criteria);

        static::assertSame($frontendRequest, $event->getFrontendRequest());
        static::assertSame($storeApiRequest, $event->getStoreApiRequest());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($channelContext->getContext(), $event->getContext());
        static::assertSame($criteria, $event->getCriteria());
    }

    public function testCriteriaDefaultsToAnEmptyCriteria(): void
    {
        $event = new LanguageRouteRequestEvent(new Request(), new Request(), Generator::generateChannelContext());

        static::assertEquals(new Criteria(), $event->getCriteria());
    }
}
