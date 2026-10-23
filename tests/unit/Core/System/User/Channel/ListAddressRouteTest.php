<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\AddressListingCriteriaEvent;
use Contena\Core\System\User\Extension\ListAddressRouteExtension;
use Contena\Core\System\User\Channel\ListAddressRoute;
use Contena\Core\System\User\Channel\ListAddressRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ListAddressRoute::class)]
class ListAddressRouteTest extends TestCase
{
    /**
     * @var Stub&ChannelRepository<UserAddressCollection>
     */
    private Stub&ChannelRepository $addressRepository;

    private CollectingEventDispatcher $eventDispatcher;

    private ListAddressRoute $route;

    protected function setUp(): void
    {
        $this->addressRepository = static::createStub(ChannelRepository::class);
        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->route = new ListAddressRoute(
            $this->addressRepository,
            $this->eventDispatcher,
            new ExtensionDispatcher(new EventDispatcher())
        );
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateChannelContext();
        $user = $context->getUser();
        static::assertNotNull($user);

        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(
            new UserAddressCollection([$context->getShippingLocation()->getAddress() ?? new UserAddressEntity()])
        );

        /** @var MockObject&ChannelRepository<UserAddressCollection> $addressRepository */
        $addressRepository = $this->createMock(ChannelRepository::class);
        $addressRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) {
                    return $criteria->hasAssociation('salutation')
                        && $criteria->hasAssociation('country')
                        && $criteria->hasAssociation('countryState');
                }),
                $context
            )
            ->willReturn($searchResult);

        $route = new ListAddressRoute($addressRepository, $this->eventDispatcher, new ExtensionDispatcher(new EventDispatcher()));

        $response = $route->load($criteria, $context, $user);

        static::assertCount(1, $response->getAddressCollection());

        $events = $this->eventDispatcher->getEvents();
        static::assertInstanceOf(AddressListingCriteriaEvent::class, $events[0]);
        static::assertSame($criteria, $events[0]->getCriteria());
        static::assertSame($context, $events[0]->getChannelContext());
    }

    public function testPublishesExtension(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = static::createStub(ListAddressRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('list-address-route.load.pre', static function (ListAddressRouteExtension $extension) use ($criteria, $context, $user, $response): void {
            static::assertSame(['criteria' => $criteria, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ListAddressRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(EventDispatcherInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($criteria, $context, $user));
    }
}
