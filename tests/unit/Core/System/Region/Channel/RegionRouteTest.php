<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Region\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Region\Channel\RegionRoute;
use Contena\Core\System\Region\Event\RegionCriteriaEvent;
use Contena\Core\System\Region\RegionCollection;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RegionRoute::class)]
class RegionRouteTest extends TestCase
{
    public function testLoad(): void
    {
        $channelContext = Generator::generateChannelContext();

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RegionCriteriaEvent::class));

        $regionRepository = $this->createMock(EntityRepository::class);
        $regionRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                0,
                new RegionCollection(),
                null,
                new Criteria(),
                $channelContext->getContext(),
            ));

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $route = new RegionRoute($regionRepository, $dispatcher, $cacheTagCollector);
        $route->load('country-id', new Request(), new Criteria(), $channelContext);
    }
}
