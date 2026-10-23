<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Extension\RegisterRouteExtension;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentification;
use Contena\Core\System\User\Validation\Constraint\UserZipCode;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\Indexing\UserNewsletterChannelsUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticChannelRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RegisterRoute::class)]
class RegisterRouteTest extends TestCase
{
    public function testAccountType(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('accountType', $create[0]);
                static::assertSame(UserEntity::ACCOUNT_TYPE_PRIVATE, $create[0]['accountType']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'shippingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testValidateShippingAddressWithBusinessAccount(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) use ($definition) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new UserZipCode(countryId: '123'));

                static::assertSame($event->getDefinition()->getProperties(), $definition->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            dataValidator: new DataValidator(Validation::createValidatorBuilder()->getValidator()),
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'id' => Uuid::randomHex(),
                'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            ],
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testValidateBillingAddressWithBusinessAccount(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition()
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new UserZipCode(countryId: null));
                $definition->add('zipcode', new Length(max: UserAddressDefinition::MAX_LENGTH_ZIPCODE));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame(UserEntity::ACCOUNT_TYPE_BUSINESS, $event->getData()->get('accountType'));
                static::assertEquals($definition->getProperties(), $event->getDefinition()->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testValidateBillingAddressVatIdsWithBusinessAccountThrowException(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $definition = new DataValidationDefinition('address.create');

        $addressValidation = static::createStub(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn($definition);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(static function (Event $event) {
            if ($event instanceof BuildValidationEvent && $event->getName() === 'framework.validation.address.create') {
                $definition = new DataValidationDefinition('address.create');

                $definition->add('company', new NotBlank());
                $definition->set('zipcode', new UserZipCode(countryId: '123'));
                $definition->add('zipcode', new Length(max: UserAddressDefinition::MAX_LENGTH_ZIPCODE));

                static::assertNull($event->getData()->get('shippingAddress'));
                static::assertSame(UserEntity::ACCOUNT_TYPE_BUSINESS, $event->getData()->get('accountType'));
                static::assertEquals($definition->getProperties(), $event->getDefinition()->getProperties());
            }

            return $event;
        });

        $register = $this->createRegisterRoute(
            eventDispatcher: $dispatcher,
            addressValidationFactory: $addressValidation,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'id' => Uuid::randomHex(),
                'countryId' => '123',
            ],
            'vatIds' => ['123'],
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
        ];

        $channelContext = Generator::generateChannelContext();

        static::expectException(UserException::class);

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testCustomFields(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertSame(['mapped' => 1], $create[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            UserDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $register = $this->createRegisterRoute(
            customFieldMapper: $customFieldMapper,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $salutationId = Uuid::randomHex();
        $salutationRepository = StaticEntityRepository::of(SalutationCollection::class, [[$salutationId]], new SalutationDefinition());

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) use ($salutationId) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('salutationId', $create[0]);
                static::assertSame($create[0]['salutationId'], $salutationId);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            salutationRepository: $salutationRepository,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => '',
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testSalutationIdIsAssignedToShippingAndBilling(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $result = static::createStub(EntitySearchResult::class);
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $result->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $salutationId = Uuid::randomHex();
        $salutationRepository = StaticEntityRepository::of(SalutationCollection::class, [[$salutationId]], new SalutationDefinition());

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) use ($salutationId) {
                static::assertCount(1, $create);
                static::assertArrayHasKey('salutationId', $create[0]);
                static::assertSame($create[0]['salutationId'], $salutationId);
                static::assertIsArray($create[0]['addresses']);
                static::assertCount(2, $create[0]['addresses']);
                foreach ($create[0]['addresses'] as $address) {
                    static::assertArrayHasKey('salutationId', $address);
                    static::assertSame($address['salutationId'], $salutationId);
                }

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $register = $this->createRegisterRoute(
            salutationRepository: $salutationRepository,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'shippingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => '',
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testRedirectParameters(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.cart.wishlistEnabled' => true,
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(true);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $userEntity->setEmail('test@test.de');

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(static function (Event $event): bool {
                    if ($event instanceof UserDoubleOptInRegistrationEvent) {
                        $query = [];
                        $queryString = \parse_url($event->getConfirmUrl(), \PHP_URL_QUERY);
                        self::assertIsString($queryString);
                        \parse_str($queryString, $query);
                        self::assertArrayHasKey('blogId', $query);
                        self::assertSame('018b906b869273fea7926f161dd23911', $query['blogId']);
                    }

                    return true;
                })
            );

        $register = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'frontendUrl' => 'http://localhost:8000',
            'redirectTo' => 'frontend.wishlist.add.after.login',
            'redirectParameters' => '{"blogId":"018b906b869273fea7926f161dd23911"}',
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testInvalidRedirectParameters(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
                'core.cart.wishlistEnabled' => true,
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(true);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $userEntity->setEmail('test@test.de');

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->atLeast(1))
            ->method('dispatch')
            ->with(
                static::callback(static function ($event): bool {
                    if ($event instanceof UserDoubleOptInRegistrationEvent) {
                        $query = [];
                        $queryString = \parse_url($event->getConfirmUrl(), \PHP_URL_QUERY);
                        self::assertIsString($queryString);
                        \parse_str($queryString, $query);
                        self::assertArrayHasKey('redirectTo', $query);
                        self::assertSame('frontend.wishlist.add.after.login', $query['redirectTo']);
                    }

                    return true;
                })
            );

        $register = $this->createRegisterRoute(
            eventDispatcher: $eventDispatcher,
            systemConfigService: $systemConfigService,
            userRepository: $userRepository
        );

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => Uuid::randomHex(),
            ],
            'frontendUrl' => 'http://localhost:8000',
            'redirectTo' => 'frontend.wishlist.add.after.login',
            'redirectParameters' => 'thisisnotajson',
        ];

        $channelContext = Generator::generateChannelContext();

        $register->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testRegisterWithBillingAddressCountryViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(true);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $userEntity->setEmail('test@test.de');

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);
        $country->setCheckVatIdPattern(false);

        $countryRepository = static::createStub(ChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'countryId' => $countryId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $salutationId,
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'countryId' => $countryId,
                'id' => Uuid::randomHex(),
                'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
                'salutationId' => $salutationId,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'vatIds' => ['123'],
            'frontendUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                $properties = $billingAddressDefinition->getProperties();

                static::assertArrayHasKey('vatIds', $properties);
                static::assertCount(3, $properties['vatIds']);

                static::assertInstanceOf(NotBlank::class, $properties['vatIds'][0]);
                static::assertInstanceOf(Type::class, $properties['vatIds'][1]);
                static::assertInstanceOf(UserVatIdentification::class, $properties['vatIds'][2]);

                return true;
            }));

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $doubleOptInService = static::createStub(DoubleOptInService::class);
        $doubleOptInService->method('mapUserDoubleOptInData')->willReturnArgument(0);

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $userRepository,
            static::createStub(ChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            $doubleOptInService,
            static::createStub(UserNewsletterChannelsUpdater::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $channelContext = Generator::generateChannelContext();

        $registerRoute->register(new RequestDataBag($data), $channelContext, false);
    }

    public function testVatIdConstraintsAreAddedBeforeUserCreateValidationEvent(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
            ],
        ]);

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);
        $country->setCheckVatIdPattern(false);

        $countryRepository = static::createStub(ChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $vatIdConstraintsOnDispatch = null;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener('framework.validation.user.create', static function (BuildValidationEvent $event) use (&$vatIdConstraintsOnDispatch): void {
            $vatIdConstraintsOnDispatch = $event->getDefinition()->getProperty('vatIds');
        });

        $dataValidator = static::createStub(DataValidator::class);
        $dataValidator
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList([
                new ConstraintViolation('Stop after validation', null, [], null, '/email', null),
            ]));

        $userDefinitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $userDefinitionFactory
            ->method('create')
            ->willReturnCallback(static fn (): DataValidationDefinition => new DataValidationDefinition('user.create'));

        $addressDefinitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $addressDefinitionFactory
            ->method('create')
            ->willReturnCallback(static fn (): DataValidationDefinition => new DataValidationDefinition('address.create'));

        $doubleOptInService = static::createStub(DoubleOptInService::class);
        $doubleOptInService->method('mapUserDoubleOptInData')->willReturnArgument(0);

        $registerRoute = new RegisterRoute(
            $eventDispatcher,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $userDefinitionFactory,
            $addressDefinitionFactory,
            $systemConfigService,
            static::createStub(EntityRepository::class),
            static::createStub(ChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $userDefinitionFactory,
            $doubleOptInService,
            static::createStub(UserNewsletterChannelsUpdater::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'countryId' => $countryId,
                'salutationId' => $salutationId,
            ],
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
        ];

        try {
            $registerRoute->register(new RequestDataBag($data), Generator::generateChannelContext(), false);
            static::fail('The stubbed violation should stop the registration.');
        } catch (ConstraintViolationException) {
        }

        static::assertIsArray($vatIdConstraintsOnDispatch);
        static::assertCount(3, $vatIdConstraintsOnDispatch);
        static::assertInstanceOf(NotBlank::class, $vatIdConstraintsOnDispatch[0]);
        static::assertInstanceOf(Type::class, $vatIdConstraintsOnDispatch[1]);
        static::assertInstanceOf(UserVatIdentification::class, $vatIdConstraintsOnDispatch[2]);
    }

    public function testRegisterWithoutBillingAddressCountryViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(true);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $userEntity->setEmail('test@test.de');

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);

        $countryRepository = static::createStub(ChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $salutationId,
            ],
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'shippingAddress' => [
                'id' => Uuid::randomHex(),
                'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
                'salutationId' => $salutationId,
            ],
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'frontendUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayHasKey('billingAddress', $subs);

                $billingAddressDefinition = $subs['billingAddress'];

                static::assertCount(6, $billingAddressDefinition->getProperties());
                static::assertArrayNotHasKey('vatIds', $billingAddressDefinition->getProperties());

                return true;
            }));

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $doubleOptInService = static::createStub(DoubleOptInService::class);
        $doubleOptInService->method('mapUserDoubleOptInData')->willReturnArgument(0);

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $userRepository,
            static::createStub(ChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            $doubleOptInService,
            static::createStub(UserNewsletterChannelsUpdater::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $channelContext = Generator::generateChannelContext();

        $registerRoute->register(new RequestDataBag($data), $channelContext, false);
    }

    #[TestDox('Rejects registration when billing address is not an associative array')]
    public function testRegisterWithNonArrayBillingAddressViolation(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.showAccountTypeSelection' => true,
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(true);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);
        $userEntity->setEmail('test@test.de');

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition(),
        );

        $countryId = Uuid::randomHex();

        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(true);

        /** @var StaticChannelRepository<CountryCollection> $countryRepository */
        $countryRepository = new StaticChannelRepository([new CountryCollection([$country])]);

        $salutationId = Uuid::randomHex();

        $data = [
            'email' => 'test@test.de',
            'billingAddress' => 'Max Mustermanns Address',
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => $salutationId,
            'lastName' => 'Mustermann',
            'firstName' => 'Max',
            'vatIds' => ['123'],
            'frontendUrl' => 'foo',
        ];

        $dataValidator = $this->createMock(DataValidator::class);

        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This value should be of type associative_array.',
                'This value should be of type {{ type }}.',
                ['{{ type }}' => 'associative_array'],
                'billingAddress',
                'billingAddress',
                'Max Mustermanns Address'
            ),
        ]);

        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with($data, static::callback(static function (DataValidationDefinition $definition) {
                $subs = $definition->getSubDefinitions();

                static::assertArrayNotHasKey('billingAddress', $subs);

                $billingAddressConstraints = $definition->getProperty('billingAddress');
                static::assertCount(1, $billingAddressConstraints);

                $billingAddressConstraint = $billingAddressConstraints[0];
                static::assertInstanceOf(Type::class, $billingAddressConstraint);
                static::assertSame('associative_array', $billingAddressConstraint->type);

                return true;
            }))
            ->willReturn($violations);

        $definitionFactory = static::createStub(DataValidationFactoryInterface::class);
        $definitionFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition());

        $registerRoute = new RegisterRoute(
            new EventDispatcher(),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $definitionFactory,
            $definitionFactory,
            $systemConfigService,
            $userRepository,
            static::createStub(ChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            $definitionFactory,
            static::createStub(DoubleOptInService::class),
            static::createStub(UserNewsletterChannelsUpdater::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $channelContext = Generator::generateChannelContext();

        try {
            $registerRoute->register(new RequestDataBag($data), $channelContext, false);
            static::fail('Expected ConstraintViolationException to be thrown');
        } catch (ConstraintViolationException $e) {
            static::assertCount(1, $e->getViolations());
            $violation = $e->getViolations()->get(0);
            static::assertSame('billingAddress', $violation->getPropertyPath());
            static::assertNotSame('', $violation->getMessage());
        }
    }

    public function testRegisterNormalizesVatIdsBeforeValidation(): void
    {
        $countryId = Uuid::randomHex();
        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setVatIdRequired(false);
        $country->setCheckVatIdPattern(true);

        $countryRepository = static::createStub(ChannelRepository::class);
        $countryRepository
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    CountryDefinition::ENTITY_NAME,
                    1,
                    new CountryCollection([$country]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext()
                )
            );

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789', 123456789], $data['vatIds']);

                    return true;
                }),
                static::isInstanceOf(DataValidationDefinition::class)
            )
            ->willReturn(new ConstraintViolationList());

        $register = $this->createRegisterRoute(
            countryRepository: $countryRepository,
            dataValidator: $dataValidator,
        );

        $data = $this->createRegistrationData();
        $data['accountType'] = UserEntity::ACCOUNT_TYPE_BUSINESS;
        $data['billingAddress']['countryId'] = $countryId;
        $data['company'] = 'Test Company';
        $data['vatIds'] = ['de 123456789', 123456789];

        $register->register(
            new RequestDataBag($data),
            Generator::generateChannelContext(),
            false
        );
    }

    #[TestDox('Accepts user names with the maximum allowed length of 255 characters')]
    public function testRegisterAcceptsMaximumNameLengths(): void
    {
        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        $maxLengthFirstName = str_repeat('M', UserDefinition::MAX_LENGTH_FIRST_NAME);
        $maxLengthLastName = str_repeat('L', UserDefinition::MAX_LENGTH_LAST_NAME);

        $data = $this->createRegistrationData([
            'firstName' => $maxLengthFirstName,
            'lastName' => $maxLengthLastName,
            'billingAddress' => [
                'firstName' => $maxLengthFirstName,
                'lastName' => $maxLengthLastName,
                'countryId' => Uuid::randomHex(),
            ],
        ]);

        $registerRoute->register(
            new RequestDataBag($data),
            Generator::generateChannelContext(),
            false
        );
    }

    #[TestDox('Rejects user names exceeding the maximum allowed length of 255 characters')]
    public function testRegisterRejectsExcessiveNameLengths(): void
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'This value is too long. It should have 255 characters or less.',
            null,
            [],
            'root',
            'firstName',
            str_repeat('T', 256)
        ));

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn($violations);

        $registerRoute = $this->createRegisterRoute(dataValidator: $dataValidator);

        $tooLongFirstName = str_repeat('T', UserDefinition::MAX_LENGTH_FIRST_NAME + 1);
        $tooLongLastName = str_repeat('L', UserDefinition::MAX_LENGTH_LAST_NAME + 1);

        $data = $this->createRegistrationData([
            'firstName' => $tooLongFirstName,
            'lastName' => $tooLongLastName,
            'billingAddress' => [
                'firstName' => $tooLongFirstName,
                'lastName' => $tooLongLastName,
                'countryId' => Uuid::randomHex(),
            ],
        ]);

        static::expectException(ConstraintViolationException::class);
        $registerRoute->register(
            new RequestDataBag($data),
            Generator::generateChannelContext(),
            false
        );
    }

    public function testRegisterPreservesShippingAddressSalutation(): void
    {
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $result = static::createStub(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new UserCollection([$userEntity]));

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('getDefinition')->willReturn(new UserDefinition());
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertCount(2, $create[0]['addresses']);
                $billingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['firstName'] === 'John'
                ))[0];
                $shippingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['firstName'] === 'Jane'
                ))[0];

                static::assertSame('billing-salutation', $billingAddress['salutationId']);
                static::assertSame('shipping-salutation', $shippingAddress['salutationId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $registerRoute = $this->createRegisterRoute(
            dataValidator: $dataValidator,
            userRepository: $userRepository
        );

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'salutationId' => 'billing-salutation',
                'shippingAddress' => [
                    'firstName' => 'Jane',
                    'lastName' => 'Doe',
                    'countryId' => Uuid::randomHex(),
                    'salutationId' => 'shipping-salutation',
                ],
            ])),
            Generator::generateChannelContext(),
            false
        );
    }

    public function testRegisterTrimsMappedAddressStringFields(): void
    {
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(true);

        $result = new EntitySearchResult(
            UserDefinition::ENTITY_NAME,
            1,
            new UserCollection([$userEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('getDefinition')->willReturn(new UserDefinition());
        $userRepository->method('search')->willReturn($result);
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) {
                static::assertCount(1, $create);
                static::assertCount(2, $create[0]['addresses']);

                $billingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['city'] === 'Berlin'
                ))[0];
                $shippingAddress = array_values(array_filter(
                    $create[0]['addresses'],
                    static fn (array $address): bool => $address['city'] === 'Hamburg'
                ))[0];

                static::assertSame('Dr.', $billingAddress['title']);
                static::assertSame('Max', $billingAddress['firstName']);
                static::assertSame('Mustermann', $billingAddress['lastName']);
                static::assertSame('Main Street 1', $billingAddress['street']);
                static::assertSame('12345', $billingAddress['zipcode']);
                static::assertSame('Contena', $billingAddress['company']);
                static::assertSame('Core', $billingAddress['department']);
                static::assertSame('123456', $billingAddress['phoneNumber']);
                static::assertSame('Line 1', $billingAddress['additionalAddressLine1']);
                static::assertSame('Line 2', $billingAddress['additionalAddressLine2']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $billingAddress['customFields']);

                static::assertSame('Ms.', $shippingAddress['title']);
                static::assertSame('Jane', $shippingAddress['firstName']);
                static::assertSame('Doe', $shippingAddress['lastName']);
                static::assertSame('Side Street 2', $shippingAddress['street']);
                static::assertSame('54321', $shippingAddress['zipcode']);
                static::assertSame('Contena Frontend', $shippingAddress['company']);
                static::assertSame('Design', $shippingAddress['department']);
                static::assertSame('654321', $shippingAddress['phoneNumber']);
                static::assertSame('Shipping Line 1', $shippingAddress['additionalAddressLine1']);
                static::assertSame('Shipping Line 2', $shippingAddress['additionalAddressLine2']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $dataValidator = static::createStub(DataValidator::class);
        $dataValidator
            ->method('getViolations')
            ->willReturn(new ConstraintViolationList());

        $addressValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturnCallback(static fn (): DataValidationDefinition => new DataValidationDefinition('address.create'));

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            UserAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $registerRoute = $this->createRegisterRoute(
            dataValidator: $dataValidator,
            addressValidationFactory: $addressValidationFactory,
            customFieldMapper: $customFieldMapper,
            userRepository: $userRepository
        );

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'guest' => true,
                'title' => '  Dr.  ',
                'firstName' => "\nMax\t",
                'lastName' => "\rMustermann ",
                'billingAddress' => [
                    'countryId' => Uuid::randomHex(),
                    'street' => "\t Main Street 1 \n",
                    'zipcode' => '  12345  ',
                    'city' => "\rBerlin\n",
                    'company' => "\tContena ",
                    'department' => "\nCore        ",
                    'phoneNumber' => "\t123456\n",
                    'additionalAddressLine1' => "\nLine 1 ",
                    'additionalAddressLine2' => "\tLine 2\r",
                    'customFields' => [
                        'note' => '  keep custom field whitespace  ',
                    ],
                ],
                'shippingAddress' => [
                    'title' => "\nMs.\t",
                    'firstName' => "\tJane ",
                    'lastName' => "          Doe\n",
                    'countryId' => Uuid::randomHex(),
                    'street' => "\nSide Street 2           ",
                    'zipcode' => "\t54321\n",
                    'city' => "\nHamburg\r",
                    'company' => "\tContena Frontend\n",
                    'department' => '    Design    ',
                    'phoneNumber' => "\n654321 ",
                    'additionalAddressLine1' => " Shipping Line 1\n",
                    'additionalAddressLine2' => "\rShipping Line 2 ",
                ],
            ])),
            Generator::generateChannelContext(),
            false
        );
    }

    public function testUpdatesNewsletterChannelIdsBeforeUserIsLoaded(): void
    {
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $result = new EntitySearchResult(
            UserDefinition::ENTITY_NAME,
            1,
            new UserCollection([$userEntity]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $createdUserId = null;
        $calls = [];

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->method('getDefinition')->willReturn(new UserDefinition());
        $userRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $create) use (&$createdUserId, &$calls) {
                $calls[] = 'create';
                $createdUserId = $create[0]['id'];

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });
        $userRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(static function () use (&$calls, $result) {
                static::assertSame(['create', 'update'], $calls);

                return $result;
            });

        $userNewsletterChannelsUpdater = $this->createMock(UserNewsletterChannelsUpdater::class);
        $userNewsletterChannelsUpdater
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (array $ids, bool $reverseUpdate) use (&$createdUserId, &$calls): void {
                $calls[] = 'update';

                static::assertNotNull($createdUserId);
                static::assertSame([$createdUserId], $ids);
                static::assertTrue($reverseUpdate);
            });

        $registerRoute = $this->createRegisterRoute(
            userRepository: $userRepository,
            userNewsletterChannelsUpdater: $userNewsletterChannelsUpdater
        );

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData()),
            Generator::generateChannelContext(),
            false
        );
    }

    /**
     * @param array<string, bool> $doubleOptInConfig
     */
    #[DataProvider('frontendUrlValidationProvider')]
    public function testFrontendUrlIsOnlyValidatedWhenDoubleOptInIsEnabled(
        array $doubleOptInConfig,
        bool $isGuest,
        bool $expectedToBeValidated
    ): void {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => array_merge([
                'core.loginRegistration.passwordMinLength' => '8',
            ], $doubleOptInConfig),
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $properties = $this->captureValidatedProperties($systemConfigService, $isGuest);

        static::assertSame($expectedToBeValidated, \array_key_exists('frontendUrl', $properties));
    }

    /**
     * @return iterable<string, array{array<string, bool>, bool, bool}>
     */
    public static function frontendUrlValidationProvider(): iterable
    {
        yield 'user, double opt-in disabled' => [[], false, false];
        yield 'user, double opt-in enabled' => [['core.loginRegistration.doubleOptInRegistration' => true], false, true];
        yield 'user, only guest order double opt-in enabled' => [['core.loginRegistration.doubleOptInGuestOrder' => true], false, false];
        yield 'guest, double opt-in disabled' => [[], true, false];
        yield 'guest, guest order double opt-in enabled' => [['core.loginRegistration.doubleOptInGuestOrder' => true], true, true];
        yield 'guest, only registration double opt-in enabled' => [['core.loginRegistration.doubleOptInRegistration' => true], true, false];
    }

    #[TestDox('frontendUrl stays unvalidated for callers opting out, even with double opt-in enabled')]
    public function testFrontendUrlValidationCanStillBeSkippedByCaller(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $properties = $this->captureValidatedProperties($systemConfigService, false, false);

        static::assertArrayNotHasKey('frontendUrl', $properties);
    }

    #[TestDox('a sales channel without domains offers no valid choice, which is why the constraint must be conditional')]
    public function testFrontendUrlChoicesAreEmptyWithoutChannelDomains(): void
    {
        $systemConfigService = new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
                'core.loginRegistration.doubleOptInRegistration' => true,
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);

        $properties = $this->captureValidatedProperties($systemConfigService, false);

        static::assertArrayHasKey('frontendUrl', $properties);
        static::assertInstanceOf(NotBlank::class, $properties['frontendUrl'][0]);
        static::assertInstanceOf(Choice::class, $properties['frontendUrl'][1]);
        static::assertSame([], $properties['frontendUrl'][1]->choices);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $additionalValidationDefinitions = new DataValidationDefinition();
        $response = new UserResponse(new UserEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('register-route.register.pre', static function (RegisterRouteExtension $extension) use ($data, $context, $additionalValidationDefinitions, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'validateFrontendUrl' => true, 'additionalValidationDefinitions' => $additionalValidationDefinitions], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new RegisterRoute(
            static::createStub(EventDispatcherInterface::class),
            static::createStub(NumberRangeValueGeneratorInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(EntityRepository::class),
            static::createStub(ChannelContextPersister::class),
            static::createStub(ChannelRepository::class),
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(DoubleOptInService::class),
            static::createStub(UserNewsletterChannelsUpdater::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->register($data, $context, true, $additionalValidationDefinitions));
    }

    /**
     * @return array<string, array<Constraint>>
     */
    private function captureValidatedProperties(
        StaticSystemConfigService $systemConfigService,
        bool $isGuest,
        bool $validateFrontendUrl = true
    ): array {
        $definition = null;

        $dataValidator = $this->createMock(DataValidator::class);
        $dataValidator
            ->expects($this->once())
            ->method('getViolations')
            ->willReturnCallback(static function (array $data, DataValidationDefinition $used) use (&$definition): ConstraintViolationList {
                $definition = $used;

                return new ConstraintViolationList();
            });

        $accountValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $accountValidationFactory->method('create')->willReturnCallback(static fn () => new DataValidationDefinition());

        $registerRoute = $this->createRegisterRoute(
            dataValidator: $dataValidator,
            systemConfigService: $systemConfigService,
            accountValidationFactory: $accountValidationFactory,
            doubleOptInService: new DoubleOptInService(
                static::createStub(EntityRepository::class),
                new EventDispatcher(),
                $systemConfigService,
                static::createStub(EntityRepository::class),
                new NativeClock(),
            ),
        );

        $channelContext = Generator::generateChannelContext();
        $channelContext->getChannel()->setDomains(new ChannelDomainCollection());

        $registerRoute->register(
            new RequestDataBag($this->createRegistrationData([
                'guest' => $isGuest,
                'frontendUrl' => 'https://example.com',
            ])),
            $channelContext,
            $validateFrontendUrl
        );

        static::assertInstanceOf(DataValidationDefinition::class, $definition);

        return $definition->getProperties();
    }

    /**
     * @return StaticEntityRepository<UserCollection>
     */
    private function createUserRepository(): StaticEntityRepository
    {
        $userEntity = new UserEntity();
        $userEntity->setDoubleOptInRegistration(false);
        $userEntity->setId('user-1');
        $userEntity->setGuest(false);

        $repository = StaticEntityRepository::of(
            UserCollection::class,
            [new UserCollection([$userEntity])],
            new UserDefinition()
        );

        return $repository;
    }

    /**
     * @param EntityRepository<SalutationCollection>|null $salutationRepository
     * @param EntityRepository<UserCollection>|StaticEntityRepository<UserCollection>|null $userRepository
     * @param ChannelRepository<CountryCollection>|null $countryRepository
     */
    private function createRegisterRoute(
        ?DataValidator $dataValidator = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidationFactoryInterface $addressValidationFactory = null,
        ?StoreApiCustomFieldMapper $customFieldMapper = null,
        ?EntityRepository $salutationRepository = null,
        ?StaticSystemConfigService $systemConfigService = null,
        EntityRepository|StaticEntityRepository|null $userRepository = null,
        ?DataValidationFactoryInterface $accountValidationFactory = null,
        ?UserNewsletterChannelsUpdater $userNewsletterChannelsUpdater = null,
        ?DoubleOptInService $doubleOptInService = null,
        ?ChannelRepository $countryRepository = null,
    ): RegisterRoute {
        $dataValidator ??= static::createStub(DataValidator::class);
        $eventDispatcher ??= new EventDispatcher();
        $accountValidationFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory ??= static::createStub(DataValidationFactoryInterface::class);
        $customFieldMapper ??= static::createStub(StoreApiCustomFieldMapper::class);
        $salutationRepository ??= static::createStub(EntityRepository::class);
        $systemConfigService ??= new StaticSystemConfigService([
            TestDefaults::CHANNEL => [
                'core.loginRegistration.passwordMinLength' => '8',
            ],
            'core.systemWideLoginRegistration.isUserBoundToChannel' => true,
        ]);
        $userRepository ??= $this->createUserRepository();
        $userNewsletterChannelsUpdater ??= static::createStub(UserNewsletterChannelsUpdater::class);
        $countryRepository ??= static::createStub(ChannelRepository::class);

        if ($doubleOptInService === null) {
            $doubleOptInService = static::createStub(DoubleOptInService::class);
            $doubleOptInService->method('mapUserDoubleOptInData')->willReturnArgument(0);
        }

        return new RegisterRoute(
            $eventDispatcher,
            static::createStub(NumberRangeValueGeneratorInterface::class),
            $dataValidator,
            $accountValidationFactory,
            $addressValidationFactory,
            $systemConfigService,
            $userRepository,
            static::createStub(ChannelContextPersister::class),
            $countryRepository,
            static::createStub(Connection::class),
            static::createStub(ChannelContextService::class),
            $customFieldMapper,
            $salutationRepository,
            static::createStub(DataValidationFactoryInterface::class),
            $doubleOptInService,
            $userNewsletterChannelsUpdater,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'salutationId' => Uuid::randomHex(),
            'billingAddress' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'countryId' => Uuid::randomHex(),
            ],
        ], $overrides);
    }
}
