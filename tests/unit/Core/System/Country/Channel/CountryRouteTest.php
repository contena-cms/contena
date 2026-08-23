<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\Event\CountryCriteriaEvent;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(CountryRoute::class)]
class CountryRouteTest extends TestCase
{
    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $this->channelContext = Generator::generateChannelContext(
            baseContext: new Context(new ChannelApiSource(Uuid::randomHex())),
            channel: $channel
        );
    }

    public function testLoad(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(1))
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryCriteriaEvent::class));

        $countryRepository = $this->createMock(ChannelRepository::class);
        $countryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                0,
                new CountryCollection(),
                null,
                new Criteria(),
                $this->channelContext->getContext(),
            ));

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $route = new CountryRoute($countryRepository, $dispatcher, $cacheTagCollector);
        $route->load(new Request(), new Criteria(), $this->channelContext);
    }
}
