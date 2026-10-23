<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\Tax\TaxDetector;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Channel\BaseChannelContext;
use Contena\Core\System\Channel\Context\AbstractBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ChannelContextFactory::class)]
class ChannelContextFactoryTest extends TestCase
{
    private const CUSTOMER_BILLING_ADDRESS_ID = 'user-billing-address-id';

    private const CUSTOMER_SHIPPING_ADDRESS_ID = 'user-shipping-address-id';

    public function testUserPaymentMethodIsOnlyUsedIfActive(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setLastPaymentMethodId(Uuid::randomHex());
        $user->setDefaultBillingAddressId(Uuid::randomHex());
        $user->setDefaultShippingAddressId(Uuid::randomHex());
        $user->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new UserAddressEntity();
        $billingAddress->setId($user->getDefaultBillingAddressId());
        $shippingAddress = new UserAddressEntity();
        $shippingAddress->setId($user->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new UserAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $currency,
            new UserGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $paymentMethodRepository = StaticEntityRepository::of(
            PaymentMethodCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($baseContext) {
                    static::assertCount(2, $criteria->getFilters());
                    static::assertEquals([
                        new EqualsFilter('active', 1),
                        new EqualsFilter('channels.id', $baseContext->getChannelId()),
                    ], $criteria->getFilters());

                    return new EntitySearchResult(
                        PaymentMethodDefinition::ENTITY_NAME,
                        0,
                        new PaymentMethodCollection(),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new PaymentMethodDefinition(),
        );

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($user) {
                    return new EntitySearchResult(
                        UserDefinition::ENTITY_NAME,
                        1,
                        new UserCollection([$user]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserDefinition(),
        );

        $addressRepository = StaticEntityRepository::of(
            UserAddressCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        UserAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserAddressDefinition(),
        );

        $options = [
            ChannelContextService::CUSTOMER_ID => $user->getId(),
        ];

        $baseChannelContextFactory = $this->createMock(AbstractBaseChannelContextFactory::class);
        $baseChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($channel->getId(), $options)
            ->willReturn($baseContext);

        $factory = new ChannelContextFactory(
            $userRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            $paymentMethodRepository,
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $channel->getId(), $options);
        static::assertSame($generatedContext->getPaymentMethod(), $baseContext->getPaymentMethod());
    }

    public function testUserIsNullIfInactive(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(false);
        $user->setLastPaymentMethodId(Uuid::randomHex());
        $user->setDefaultBillingAddressId(Uuid::randomHex());
        $user->setDefaultShippingAddressId(Uuid::randomHex());
        $user->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new UserAddressEntity();
        $billingAddress->setId($user->getDefaultBillingAddressId());
        $shippingAddress = new UserAddressEntity();
        $shippingAddress->setId($user->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new UserAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $currency,
            new UserGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $options = [
            ChannelContextService::CUSTOMER_ID => $user->getId(),
        ];

        $baseChannelContextFactory = $this->createMock(AbstractBaseChannelContextFactory::class);
        $baseChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($channel->getId(), $options)
            ->willReturn($baseContext);

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($user) {
                    return new EntitySearchResult(
                        UserDefinition::ENTITY_NAME,
                        1,
                        new UserCollection([$user]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserDefinition(),
        );

        $addressRepository = StaticEntityRepository::of(
            UserAddressCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        UserAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserAddressDefinition(),
        );

        $factory = new ChannelContextFactory(
            $userRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $channel->getId(), $options);
        static::assertNull($generatedContext->getUser());
    }

    public function testUserIsSetIfActive(): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $basePaymentMethod = new PaymentMethodEntity();
        $basePaymentMethod->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setLastPaymentMethodId(Uuid::randomHex());
        $user->setDefaultBillingAddressId(Uuid::randomHex());
        $user->setDefaultShippingAddressId(Uuid::randomHex());
        $user->setGroupId(Uuid::randomHex());

        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);

        $billingAddress = new UserAddressEntity();
        $billingAddress->setId($user->getDefaultBillingAddressId());
        $shippingAddress = new UserAddressEntity();
        $shippingAddress->setId($user->getDefaultShippingAddressId());
        $shippingAddress->setCountry($country);
        $addresses = new UserAddressCollection([$billingAddress, $shippingAddress]);

        $baseContext = new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $currency,
            new UserGroupEntity(),
            new TaxCollection(),
            $basePaymentMethod,
            new ShippingMethodEntity(),
            new ShippingLocation($country, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $options = [
            ChannelContextService::CUSTOMER_ID => $user->getId(),
        ];

        $baseChannelContextFactory = $this->createMock(AbstractBaseChannelContextFactory::class);
        $baseChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($channel->getId(), $options)
            ->willReturn($baseContext);

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($user) {
                    return new EntitySearchResult(
                        UserDefinition::ENTITY_NAME,
                        1,
                        new UserCollection([$user]),
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserDefinition(),
        );

        $addressRepository = StaticEntityRepository::of(
            UserAddressCollection::class,
            [
                static function (Criteria $criteria, Context $context) use ($addresses) {
                    return new EntitySearchResult(
                        UserAddressDefinition::ENTITY_NAME,
                        2,
                        $addresses,
                        null,
                        $criteria,
                        $context
                    );
                },
            ],
            new UserAddressDefinition(),
        );

        $factory = new ChannelContextFactory(
            $userRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $channel->getId(), $options);
        static::assertSame($user, $generatedContext->getUser());
    }

    /**
     * @param list<'billing'|'shipping'> $danglingDefaults
     * @param 'shipping-address'|'billing-address'|'sales-channel' $expectedShippingLocation
     */
    #[DataProvider('danglingDefaultAddressProvider')]
    public function testUserWithDanglingDefaultAddressDoesNotThrow(array $danglingDefaults, bool $expectsBillingAddress, bool $expectsShippingAddress, string $expectedShippingLocation): void
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setDefaultBillingAddressId(Uuid::randomHex());
        $user->setDefaultShippingAddressId(Uuid::randomHex());
        $user->setGroupId(Uuid::randomHex());

        $billingCountry = new CountryEntity();
        $billingCountry->setId(Uuid::randomHex());
        $shippingCountry = new CountryEntity();
        $shippingCountry->setId(Uuid::randomHex());
        $channelCountry = new CountryEntity();
        $channelCountry->setId(Uuid::randomHex());
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig(2, 0.01, true));

        $addresses = new UserAddressCollection();

        if (!\in_array('billing', $danglingDefaults, true)) {
            $billingAddress = new UserAddressEntity();
            $billingAddress->setId($user->getDefaultBillingAddressId());
            $billingAddress->setCountry($billingCountry);
            $addresses->add($billingAddress);
        }

        if (!\in_array('shipping', $danglingDefaults, true)) {
            $shippingAddress = new UserAddressEntity();
            $shippingAddress->setId($user->getDefaultShippingAddressId());
            $shippingAddress->setCountry($shippingCountry);
            $addresses->add($shippingAddress);
        }

        $baseShippingLocation = new ShippingLocation($channelCountry, null, null);

        $baseContext = new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $currency,
            new UserGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            $baseShippingLocation,
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $userRepository = StaticEntityRepository::of(
            UserCollection::class,
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    UserDefinition::ENTITY_NAME,
                    1,
                    new UserCollection([$user]),
                    null,
                    $criteria,
                    $context
                ),
            ],
            new UserDefinition(),
        );

        $addressRepository = StaticEntityRepository::of(
            UserAddressCollection::class,
            [
                static fn (Criteria $criteria, Context $context) => new EntitySearchResult(
                    UserAddressDefinition::ENTITY_NAME,
                    $addresses->count(),
                    $addresses,
                    null,
                    $criteria,
                    $context
                ),
            ],
            new UserAddressDefinition(),
        );

        $options = [
            ChannelContextService::CUSTOMER_ID => $user->getId(),
        ];

        $baseChannelContextFactory = $this->createMock(AbstractBaseChannelContextFactory::class);
        $baseChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with($channel->getId(), $options)
            ->willReturn($baseContext);

        $factory = new ChannelContextFactory(
            $userRepository,
            static::createStub(EntityRepository::class),
            $addressRepository,
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseChannelContextFactory,
        );

        $generatedContext = $factory->create(Uuid::randomHex(), $channel->getId(), $options);

        $generatedUser = $generatedContext->getUser();
        static::assertNotNull($generatedUser);
        static::assertSame($expectsBillingAddress, $generatedUser->getActiveBillingAddress() !== null);
        static::assertSame($expectsShippingAddress, $generatedUser->getActiveShippingAddress() !== null);

        $expectedCountry = match ($expectedShippingLocation) {
            'shipping-address' => $shippingCountry,
            'billing-address' => $billingCountry,
            'sales-channel' => $channelCountry,
        };

        static::assertSame($expectedCountry, $generatedContext->getShippingLocation()->getCountry());
    }

    /**
     * @return iterable<string, array{list<'billing'|'shipping'>, bool, bool, 'shipping-address'|'billing-address'|'sales-channel'}>
     */
    public static function danglingDefaultAddressProvider(): iterable
    {
        yield 'dangling default billing address' => [['billing'], false, true, 'shipping-address'];
        yield 'dangling default shipping address' => [['shipping'], true, false, 'billing-address'];
        yield 'both default addresses dangling' => [['billing', 'shipping'], false, false, 'sales-channel'];
    }

    public function testInjectedShippingAddressBecomesTheActiveShippingAddress(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([ChannelContextService::SHIPPING_ADDRESS => $injectedAddress]);

        $user = $context->getUser();
        static::assertNotNull($user);
        static::assertSame($injectedAddress, $user->getActiveShippingAddress());
        static::assertSame($injectedAddress, $context->getShippingLocation()->getAddress());
        static::assertSame($injectedAddress->getCountry(), $context->getShippingLocation()->getCountry());
        static::assertSame(self::CUSTOMER_BILLING_ADDRESS_ID, $user->getActiveBillingAddress()?->getId());
    }

    public function testInjectedBillingAddressBecomesTheActiveBillingAddress(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([ChannelContextService::BILLING_ADDRESS => $injectedAddress]);

        $user = $context->getUser();
        static::assertNotNull($user);
        static::assertSame($injectedAddress, $user->getActiveBillingAddress());
        static::assertSame(self::CUSTOMER_SHIPPING_ADDRESS_ID, $user->getActiveShippingAddress()?->getId());
    }

    #[DataProvider('addressOptionProvider')]
    public function testExistingAddressIdTakesPrecedenceOverTheInjectedAddress(string $idOption, string $addressOption, string $userAddressId): void
    {
        $context = $this->createContext([
            $idOption => $userAddressId,
            $addressOption => $this->createInjectedAddress(),
        ]);

        $user = $context->getUser();
        static::assertNotNull($user);

        $activeAddress = $addressOption === ChannelContextService::BILLING_ADDRESS
            ? $user->getActiveBillingAddress()
            : $user->getActiveShippingAddress();

        static::assertSame($userAddressId, $activeAddress?->getId());
    }

    #[DataProvider('unknownAddressIdProvider')]
    public function testUnknownAddressIdFallsBackToTheInjectedAddress(string $idOption, string $addressOption): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([
            $idOption => Uuid::randomHex(),
            $addressOption => $injectedAddress,
        ]);

        $user = $context->getUser();
        static::assertNotNull($user);

        $activeAddress = $addressOption === ChannelContextService::BILLING_ADDRESS
            ? $user->getActiveBillingAddress()
            : $user->getActiveShippingAddress();

        static::assertSame($injectedAddress, $activeAddress);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function addressOptionProvider(): iterable
    {
        yield 'billing address' => [
            ChannelContextService::BILLING_ADDRESS_ID,
            ChannelContextService::BILLING_ADDRESS,
            self::CUSTOMER_BILLING_ADDRESS_ID,
        ];

        yield 'shipping address' => [
            ChannelContextService::SHIPPING_ADDRESS_ID,
            ChannelContextService::SHIPPING_ADDRESS,
            self::CUSTOMER_SHIPPING_ADDRESS_ID,
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unknownAddressIdProvider(): iterable
    {
        yield 'billing address' => [ChannelContextService::BILLING_ADDRESS_ID, ChannelContextService::BILLING_ADDRESS];
        yield 'shipping address' => [ChannelContextService::SHIPPING_ADDRESS_ID, ChannelContextService::SHIPPING_ADDRESS];
    }

    public function testInjectedShippingAddressDefinesTheShippingLocationWithoutUser(): void
    {
        $injectedAddress = $this->createInjectedAddress();

        $context = $this->createContext([ChannelContextService::SHIPPING_ADDRESS => $injectedAddress], withUser: false);

        static::assertNull($context->getUser());
        static::assertSame($injectedAddress, $context->getShippingLocation()->getAddress());
        static::assertSame($injectedAddress->getCountry(), $context->getShippingLocation()->getCountry());
    }

    private function createInjectedAddress(): UserAddressEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());

        $address = new UserAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setZipcode('48624');
        $address->setCountryId($country->getId());
        $address->setCountry($country);

        return $address;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createContext(array $options, bool $withUser = true): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setDefaultBillingAddressId(self::CUSTOMER_BILLING_ADDRESS_ID);
        $user->setDefaultShippingAddressId(self::CUSTOMER_SHIPPING_ADDRESS_ID);
        $user->setGroupId(Uuid::randomHex());

        $channelCountry = new CountryEntity();
        $channelCountry->setId(Uuid::randomHex());

        $billingAddress = new UserAddressEntity();
        $billingAddress->setId(self::CUSTOMER_BILLING_ADDRESS_ID);
        $billingAddress->setCountry($channelCountry);

        $shippingAddress = new UserAddressEntity();
        $shippingAddress->setId(self::CUSTOMER_SHIPPING_ADDRESS_ID);
        $shippingAddress->setCountry($channelCountry);

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setFactor(1);
        $currency->setItemRounding(new CashRoundingConfig(2, 0.01, true));
        $currency->setTotalRounding(new CashRoundingConfig(2, 0.01, true));

        $baseContext = new BaseChannelContext(
            Context::createDefaultContext(new ChannelApiSource($channel->getId())),
            $channel,
            $currency,
            new UserGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation($channelCountry, null, null),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            Generator::createLanguageInfo(),
            MeasurementUnits::createDefaultUnits()
        );

        $baseChannelContextFactory = static::createStub(AbstractBaseChannelContextFactory::class);
        $baseChannelContextFactory->method('create')->willReturn($baseContext);

        $factory = new ChannelContextFactory(
            new StaticEntityRepository([new UserCollection([$user])], new UserDefinition()),
            static::createStub(EntityRepository::class),
            new StaticEntityRepository([new UserAddressCollection([$billingAddress, $shippingAddress])], new UserAddressDefinition()),
            static::createStub(EntityRepository::class),
            static::createStub(TaxDetector::class),
            [],
            static::createStub(EventDispatcherInterface::class),
            static::createStub(EntityRepository::class),
            $baseChannelContextFactory,
        );

        if ($withUser) {
            $options[ChannelContextService::CUSTOMER_ID] = $user->getId();
        }

        return $factory->create(Uuid::randomHex(), $channel->getId(), $options);
    }
}
