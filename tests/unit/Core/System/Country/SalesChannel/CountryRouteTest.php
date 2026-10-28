<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Api\Context\SalesChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\Event\CountryCriteriaEvent;
use Contena\Core\System\Country\Extension\CountryRouteExtension;
use Contena\Core\System\Country\SalesChannel\CountryRoute;
use Contena\Core\System\Country\SalesChannel\CountryRouteResponse;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryRoute::class)]
class CountryRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->salesChannelContext = Generator::generateSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $salesChannel
        );
    }

    public function testLoad(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(1))
            ->method('dispatch')
            ->with(static::isInstanceOf(CountryCriteriaEvent::class));

        $countryRepository = $this->createMock(SalesChannelRepository::class);
        $countryRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'country',
                0,
                new CountryCollection(),
                null,
                new Criteria(),
                $this->salesChannelContext->getContext(),
            ));

        $cacheTagCollector = static::createStub(CacheTagCollector::class);

        $route = new CountryRoute($countryRepository, $dispatcher, $cacheTagCollector, new ExtensionDispatcher(new EventDispatcher()));
        $route->load(new Request(), new Criteria(), $this->salesChannelContext);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $response = static::createStub(CountryRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('country-route.load.pre', function (CountryRouteExtension $extension) use ($request, $criteria, $response): void {
            static::assertSame(['request' => $request, 'criteria' => $criteria, 'context' => $this->salesChannelContext], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new CountryRoute(
            static::createStub(SalesChannelRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $criteria, $this->salesChannelContext));
    }
}
