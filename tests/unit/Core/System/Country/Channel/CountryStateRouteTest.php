<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\Event\CountryStateCriteriaEvent;
use Contena\Core\System\Country\Extension\CountryStateRouteExtension;
use Contena\Core\System\Country\Channel\CountryStateRoute;
use Contena\Core\System\Country\Channel\CountryStateRouteResponse;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(CountryStateRoute::class)]
class CountryStateRouteTest extends TestCase
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
            ->with(static::isInstanceOf(CountryStateCriteriaEvent::class));

        $countryStateRepository = $this->createMock(EntityRepository::class);
        $countryStateRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country_state',
                0,
                new CountryStateCollection(),
                null,
                new Criteria(),
                $this->channelContext->getContext(),
            ));

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $route = new CountryStateRoute($countryStateRepository, $dispatcher, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));
        $route->load(Uuid::randomHex(), new Request(), new Criteria(), $this->channelContext);
    }

    public function testPublishesExtension(): void
    {
        $countryId = Uuid::randomHex();
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(CountryStateRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('country-state-route.load.pre', function (CountryStateRouteExtension $extension) use ($countryId, $request, $criteria, $response): void {
            static::assertSame(['countryId' => $countryId, 'request' => $request, 'criteria' => $criteria, 'context' => $this->channelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CountryStateRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($countryId, $request, $criteria, $this->channelContext));
    }
}
