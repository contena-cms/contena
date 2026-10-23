<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Channel\UpsertAddressRoute;
use Contena\Core\System\User\Channel\UpsertAddressRouteResponse;
use Contena\Core\System\User\Extension\UpsertAddressRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
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
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturn('1');

        $result = static::createStub(EntitySearchResult::class);
        $address = new UserAddressEntity();
        $address->setId(Uuid::randomHex());
        $result->method('getEntities')->willReturn(new UserAddressCollection([$address]));

        $channelAddressRepository = static::createStub(ChannelRepository::class);
        $channelAddressRepository->method('search')->willReturn($result);

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) {
                static::assertSame(['mapped' => 1], $data[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            UserAddressDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $channelAddressRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn(TestDefaults::CHANNEL);

        $user = new UserEntity();
        $user->setId('user1');

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ]);

        $upsert->upsert(null, $data, $channelContext, $user);
    }

    public function testAddressStringFieldsAreTrimmedBeforeUpsert(): void
    {
        $countryId = Uuid::randomHex();
        $salutationId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) use ($countryId, $salutationId, $userId) {
                static::assertCount(1, $data);
                static::assertSame($salutationId, $data[0]['salutationId']);
                static::assertSame('Max', $data[0]['firstName']);
                static::assertSame('Mustermann', $data[0]['lastName']);
                static::assertSame('Main Street 1', $data[0]['street']);
                static::assertSame('12345', $data[0]['zipcode']);
                static::assertSame('Berlin', $data[0]['city']);
                static::assertSame('Contena', $data[0]['company']);
                static::assertSame('Core', $data[0]['department']);
                static::assertSame('Dr.', $data[0]['title']);
                static::assertSame('123456', $data[0]['phoneNumber']);
                static::assertSame('Line 1', $data[0]['additionalAddressLine1']);
                static::assertSame('Line 2', $data[0]['additionalAddressLine2']);
                static::assertSame($countryId, $data[0]['countryId']);
                static::assertNull($data[0]['countryStateId']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $data[0]['customFields']);
                static::assertSame($userId, $data[0]['userId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $address = new UserAddressEntity();
        $address->setId(Uuid::randomHex());

        $channelAddressRepository = static::createStub(ChannelRepository::class);
        $channelAddressRepository->method('search')->willReturn(
            new EntitySearchResult(
                UserAddressDefinition::ENTITY_NAME,
                1,
                new UserAddressCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $addressValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition('address.create'));

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            UserAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $channelAddressRepository,
            static::createStub(DataValidator::class),
            new EventDispatcher(),
            $addressValidationFactory,
            static::createStub(SystemConfigService::class),
            $customFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $user = new UserEntity();
        $user->setId($userId);

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => $salutationId,
            'name' => "\nMax\t",
            'lastName' => "\rMustermann ",
            'street' => "\t Main Street 1 \n",
            'zipcode' => "    12345\t",
            'city' => "\rBerlin\n",
            'countryId' => $countryId,
            'countryStateId' => '',
            'company' => "\tContena ",
            'department' => "\nCore    ",
            'title' => "\tDr.\n",
            'phoneNumber' => "\t123456\n",
            'additionalAddressLine1' => '        Line 1         ',
            'additionalAddressLine2' => "    Line 2\r",
            'customFields' => [
                'note' => '  keep custom field whitespace  ',
            ],
        ]);

        $upsert->upsert(null, $data, Generator::generateChannelContext(), $user);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $address = new UserAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setSalutationId($salutationId);

        $channelAddressRepository = $this->createMock(ChannelRepository::class);
        $channelAddressRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'user_address',
                1,
                new EntityCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = static::createStub(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $systemConfigService = static::createStub(SystemConfigService::class);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $channelAddressRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            $systemConfigService,
            static::createStub(StoreApiCustomFieldMapper::class),
            $salutationRepository,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $user = new UserEntity();
        $user->setId('user1');

        $data = new RequestDataBag([
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '',
        ]);

        $upsert->upsert(null, $data, static::createStub(ChannelContext::class), $user);
    }

    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new UpsertAddressRouteResponse(new UserAddressEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('upsert-address-route.upsert.pre', static function (UpsertAddressRouteExtension $extension) use ($addressId, $data, $context, $user, $response): void {
            static::assertSame(['addressId' => $addressId, 'data' => $data, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new UpsertAddressRoute(
            static::createStub(EntityRepository::class),
            static::createStub(ChannelRepository::class),
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->upsert($addressId, $data, $context, $user));
    }
}
