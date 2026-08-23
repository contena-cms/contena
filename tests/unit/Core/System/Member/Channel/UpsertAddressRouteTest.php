<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressDefinition;
use Contena\Core\System\Member\Channel\ChannelMemberAddressCollection;
use Contena\Core\System\Member\Channel\ChannelMemberAddressEntity;
use Contena\Core\System\Member\Channel\UpsertAddressRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UpsertAddressRoute::class)]
class UpsertAddressRouteTest extends TestCase
{
    public function testCustomFields(): void
    {
        $channelAddressRepository = $this->createChannelAddressRepository();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data): EntityWrittenContainerEvent {
                static::assertSame(['mapped' => 1], $data[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
            });

        $customFieldMapper = new ChannelApiCustomFieldMapper(static::createStub(Connection::class), [
            MemberAddressDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => CustomFieldTypes::INT],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $channelAddressRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            $this->createAddressValidationFactory(),
            $customFieldMapper,
        );

        $member = new MemberEntity();
        $member->setId('member1');

        $data = new RequestDataBag([
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ]);

        $upsert->upsert(null, $data, Generator::generateChannelContext(member: $member), $member);
    }

    public function testAddressStringFieldsAreTrimmedBeforeUpsert(): void
    {
        $countryId = Uuid::randomHex();
        $memberId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) use ($countryId, $memberId): EntityWrittenContainerEvent {
                static::assertCount(1, $data);
                static::assertSame('Max', $data[0]['firstName']);
                static::assertSame('Mustermann', $data[0]['lastName']);
                static::assertSame('Main Street 1', $data[0]['street']);
                static::assertSame('12345', $data[0]['zipcode']);
                static::assertSame('Berlin', $data[0]['city']);
                static::assertSame('Dr.', $data[0]['title']);
                static::assertSame('123456', $data[0]['phoneNumber']);
                static::assertSame('Line 1', $data[0]['additionalAddressLine1']);
                static::assertSame('Line 2', $data[0]['additionalAddressLine2']);
                static::assertSame($countryId, $data[0]['countryId']);
                static::assertNull($data[0]['regionId']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $data[0]['customFields']);
                static::assertSame($memberId, $data[0]['memberId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
            });

        $customFieldMapper = new ChannelApiCustomFieldMapper(static::createStub(Connection::class), [
            MemberAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => CustomFieldTypes::TEXT],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $this->createChannelAddressRepository(),
            static::createStub(DataValidator::class),
            new EventDispatcher(),
            $this->createAddressValidationFactory(),
            $customFieldMapper,
        );

        $member = new MemberEntity();
        $member->setId($memberId);

        $data = new RequestDataBag([
            'firstName' => "\nMax\t",
            'lastName' => "\rMustermann ",
            'street' => "\t Main Street 1 \n",
            'zipcode' => "    12345\t",
            'city' => "\rBerlin\n",
            'countryId' => $countryId,
            'regionId' => '',
            'title' => "\tDr.\n",
            'phoneNumber' => "\t123456\n",
            'additionalAddressLine1' => '        Line 1         ',
            'additionalAddressLine2' => "    Line 2\r",
            'customFields' => [
                'note' => '  keep custom field whitespace  ',
            ],
        ]);

        $upsert->upsert(null, $data, Generator::generateChannelContext(member: $member), $member);
    }

    /**
     * @return ChannelRepository<ChannelMemberAddressCollection>
     */
    private function createChannelAddressRepository(): ChannelRepository
    {
        $address = new ChannelMemberAddressEntity();
        $address->setId(Uuid::randomHex());
        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ChannelMemberAddressCollection([$address]));

        $repository = static::createStub(ChannelRepository::class);
        $repository->method('search')->willReturn($result);

        return $repository;
    }

    private function createAddressValidationFactory(): DataValidationFactoryInterface
    {
        $factory = static::createStub(DataValidationFactoryInterface::class);
        $factory->method('create')->willReturn(new DataValidationDefinition('address.create'));

        return $factory;
    }
}
