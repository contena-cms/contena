<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Member\Channel\ChannelMemberAddressCollection;
use Contena\Core\System\Member\Channel\ChannelMemberAddressEntity;
use Contena\Core\System\Member\Channel\ListAddressRoute;
use Contena\Core\System\Member\Event\AddressListingCriteriaEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[CoversClass(ListAddressRoute::class)]
class ListAddressRouteTest extends TestCase
{
    /**
     * @var ChannelRepository<ChannelMemberAddressCollection>&Stub
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
        $member = new MemberEntity();
        $context = Generator::generateChannelContext(member: $member);

        $address = new ChannelMemberAddressEntity();
        $address->setId(Uuid::randomHex());
        $searchResult = static::createStub(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn(
            new ChannelMemberAddressCollection([$address])
        );

        /** @var ChannelRepository<ChannelMemberAddressCollection>&MockObject $addressRepository */
        $addressRepository = $this->createMock(ChannelRepository::class);
        $addressRepository->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->hasAssociation('country')
                        && $criteria->hasAssociation('region');
                }),
                $context
            )
            ->willReturn($searchResult);

        $route = new ListAddressRoute($addressRepository, $this->eventDispatcher);

        $response = $route->load($criteria, $context, $member);

        static::assertCount(1, $response->getAddressCollection());

        $events = $this->eventDispatcher->getEvents();
        static::assertInstanceOf(AddressListingCriteriaEvent::class, $events[0]);
        static::assertSame($criteria, $events[0]->getCriteria());
        static::assertSame($context, $events[0]->getChannelContext());
    }
}
