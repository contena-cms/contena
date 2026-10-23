<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\BaseChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ContextFactory;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingDefinition;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 *
 * @phpstan-import-type ContextOptions from BaseChannelContextFactory
 */
#[CoversClass(BaseChannelContextFactory::class)]
class BaseChannelContextFactoryTest extends TestCase
{
    /**
     * @param ContextOptions $options
     * @param array<string, array<mixed>> $entitySearchResult
     * @param false|array<string, mixed> $fetchDataResult
     */
    #[DataProvider('factoryCreationDataProvider')]
    public function testCreate(
        array $options,
        false|array $fetchDataResult,
        false|string $fetchParentLanguageResult,
        array $entitySearchResult,
        ?\Exception $expectedException = null,
    ): void {
        if ($expectedException !== null) {
            $this->expectExceptionObject($expectedException);
        }

        $userGroupRepository = StaticEntityRepository::of(UserGroupCollection::class, [new UserGroupCollection($entitySearchResult[UserGroupDefinition::ENTITY_NAME] ?? [])]);
        $countryRepository = StaticEntityRepository::of(CountryCollection::class, [new CountryCollection($entitySearchResult[CountryDefinition::ENTITY_NAME] ?? [])]);
        $taxRepository = StaticEntityRepository::of(TaxCollection::class, [new TaxCollection($entitySearchResult[TaxDefinition::ENTITY_NAME] ?? [])]);
        $paymentMethodRepository = StaticEntityRepository::of(PaymentMethodCollection::class, [new PaymentMethodCollection($entitySearchResult[PaymentMethodDefinition::ENTITY_NAME] ?? [])]);
        $shippingMethodRepository = StaticEntityRepository::of(ShippingMethodCollection::class, [new ShippingMethodCollection($entitySearchResult[ShippingMethodDefinition::ENTITY_NAME] ?? [])]);
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [new ChannelCollection($entitySearchResult[ChannelDefinition::ENTITY_NAME] ?? [])]);
        $countryStateRepository = StaticEntityRepository::of(CountryStateCollection::class, [new CountryStateCollection($entitySearchResult[CountryStateDefinition::ENTITY_NAME] ?? [])]);
        $currencyCountryRepository = StaticEntityRepository::of(CurrencyCountryRoundingCollection::class, [new CurrencyCountryRoundingCollection($entitySearchResult[CurrencyCountryRoundingDefinition::ENTITY_NAME] ?? [])]);
        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> $languageRepository */
        $languageRepository = new StaticEntityRepository([new EntityCollection($entitySearchResult[LanguageDefinition::ENTITY_NAME] ?? [])]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($fetchDataResult);

        if ($fetchDataResult === false) {
            $connection->expects($this->never())->method('createQueryBuilder');
        }

        if ($fetchParentLanguageResult !== false) {
            $result = $this->createMock(Result::class);
            $result->expects($this->once())->method('fetchOne')->willReturn($fetchParentLanguageResult);
            $connection->expects($this->once())->method('executeQuery')->willReturn($result);
            $connection->expects($this->atMost(1))->method('createQueryBuilder')->willReturn(new QueryBuilder($connection));
        } else {
            $result = $this->createMock(Result::class);
            $result->expects($this->atMost(1))->method('fetchOne')->willReturn(false);
            $connection->expects($this->atMost(1))->method('executeQuery')->willReturn($result);
            $connection->expects($this->atMost(1))->method('createQueryBuilder')->willReturn(new QueryBuilder($connection));
        }

        $contextProvider = new ContextFactory($connection, new CollectingEventDispatcher());

        $factory = new BaseChannelContextFactory(
            $channelRepository,
            $userGroupRepository,
            $countryRepository,
            $taxRepository,
            $paymentMethodRepository,
            $shippingMethodRepository,
            $countryStateRepository,
            $currencyCountryRepository,
            $contextProvider,
            $languageRepository,
        );

        $context = $factory->create(TestDefaults::CHANNEL, $options);
        $channel = $context->getChannel();

        static::assertSame($channel->getCurrencyId(), $channel->getCurrency()?->getId());
        static::assertSame($options[ChannelContextService::CURRENCY_ID] ?? $channel->getCurrencyId(), $context->getCurrencyId());
        static::assertSame($context->getCurrentUserGroup(), $channel->getUserGroup());
    }

    /**
     * @return iterable<string, array<string, mixed>>
     */
    public static function factoryCreationDataProvider(): iterable
    {
        $invalidChannelId = Uuid::randomHex();
        $paymentMethodId = Uuid::randomHex();
        $userGroupId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $countryStateId = Uuid::randomHex();
        $countryId = Uuid::randomHex();
        $anotherLanguageId = Uuid::randomHex();

        $locale = new PartialEntity();
        $locale->assign([
            'code' => 'en-GB',
        ]);

        $language = new PartialEntity();
        $language->assign([
            'id' => Defaults::LANGUAGE_SYSTEM,
            'name' => 'English',
            'locale' => $locale,
            'translationCode' => $locale,
        ]);

        $channelEntity = new ChannelEntity();
        $channelEntity->setUniqueIdentifier(TestDefaults::CHANNEL);
        $channelEntity->setUserGroupId($userGroupId);
        $channelEntity->setPaymentMethodId($paymentMethodId);
        $channelEntity->setShippingMethodId($shippingMethodId);
        $channelEntity->setCurrencyId($currencyId);
        $channelEntity->setCountryId($countryId);
        $channelEntity->setMeasurementUnits(MeasurementUnits::createDefaultUnits());
        $domains = new ChannelDomainCollection();
        $domain = new ChannelDomainEntity();
        $domain->setId('domain-id');
        $domain->setMeasurementUnits(MeasurementUnits::createDefaultUnits());
        $domains->add($domain);
        $channelEntity->setDomains($domains);

        $currency = new CurrencyEntity();
        $rounding = new CashRoundingConfig(1, 1, true);
        $currency->setUniqueIdentifier($currencyId);
        $currency->setTotalRounding($rounding);
        $currency->setItemRounding($rounding);
        $currency->setId($currencyId);
        $currency->setFactor(1);
        $channelEntity->setCurrencies(new CurrencyCollection([$currency]));

        $country = new CountryEntity();
        $country->setUniqueIdentifier($countryId);
        $country->setId($countryId);

        $countryState = new CountryStateEntity();
        $countryState->setUniqueIdentifier($countryStateId);
        $countryState->setCountryId($countryId);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setUniqueIdentifier($paymentMethodId);
        $paymentMethod->setId($paymentMethodId);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setUniqueIdentifier($shippingMethodId);
        $shippingMethod->setId($shippingMethodId);
        $channelEntity->setShippingMethod($shippingMethod);

        $userGroup = new UserGroupEntity();
        $userGroup->setUniqueIdentifier($userGroupId);

        yield 'no context data' => [
            'options' => [],
            'fetchDataResult' => false,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'expectedException' => ChannelException::noContextData(TestDefaults::CHANNEL),
        ];

        yield 'provided language not available' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => $invalidChannelId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'expectedException' => ChannelException::providedLanguageNotAvailable($invalidChannelId, [Defaults::LANGUAGE_SYSTEM]),
        ];

        yield 'language id is not uuid' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => 'not-an-uuid',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'expectedException' => ChannelException::invalidLanguageId(),
        ];

        yield 'language id not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [],
            'expectedException' => ChannelException::languageNotFound('3ebb5fe2e29a4d70aa5854ce7ce3e20b'),
        ];

        yield 'sales channel not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => '3ebb5fe2e29a4d70aa5854ce7ce3e20b,' . Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => Uuid::randomHex(),
            'entitySearchResult' => [],
            'expectedException' => ChannelException::channelNotFound(TestDefaults::CHANNEL),
        ];

        yield 'currency id is not uuid' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => 'not-an-uuid',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
            ],
            'expectedException' => ChannelException::invalidCurrencyId(),
        ];

        yield 'currency not available in sales channel' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => '3ebb5fe2e29a4d70aa5854ce7ce3e20b',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
            ],
            'expectedException' => ChannelException::currencyNotFound('3ebb5fe2e29a4d70aa5854ce7ce3e20b'),
        ];

        $channelWithoutCurrency = new ChannelEntity();
        $channelWithoutCurrency->setUniqueIdentifier(TestDefaults::CHANNEL);
        $channelWithoutCurrency->setCurrencyId('b7d2554b0ce847cd82f3ac9bd1c0dfca');

        yield 'default currency not loaded' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelWithoutCurrency,
                ],
            ],
            'expectedException' => ChannelException::currencyNotFound('b7d2554b0ce847cd82f3ac9bd1c0dfca'),
        ];

        yield 'user group not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
            ],
            'expectedException' => ChannelException::userGroupNotFound($userGroupId),
        ];

        yield 'country state id is not uuid' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_STATE_ID => 'not-an-uuid',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'expectedException' => ChannelException::invalidCountryStateId(),
        ];

        yield 'country state not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_STATE_ID => $countryStateId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'expectedException' => ChannelException::countryStateNotFound($countryStateId),
        ];

        yield 'country not found if country state ID is given' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_STATE_ID => $countryStateId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryStateDefinition::ENTITY_NAME => [
                    $countryStateId => $countryState,
                ],
            ],
            'expectedException' => ChannelException::countryNotFound($countryId),
        ];

        yield 'country id is not uuid' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => 'not-an-uuid',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'expectedException' => ChannelException::invalidCountryId(),
        ];

        yield 'country not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
            ],
            'expectedException' => ChannelException::countryNotFound($countryId),
        ];

        yield 'payment method not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                UserGroupDefinition::ENTITY_NAME => [
                    $userGroupId => $userGroup,
                ],
            ],
            'expectedException' => ChannelException::unknownPaymentMethod($paymentMethodId),
        ];

        yield 'shipping method not found' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                PaymentMethodDefinition::ENTITY_NAME => [
                    $paymentMethodId => $paymentMethod,
                ],
                UserGroupDefinition::ENTITY_NAME => [
                    $userGroupId => $userGroup,
                ],
            ],
            'expectedException' => ChannelException::shippingMethodNotFound($shippingMethodId),
        ];

        yield 'missing sales channel language' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => $anotherLanguageId,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM . ',' . $anotherLanguageId,
            ],
            'fetchParentLanguageResult' => Defaults::LANGUAGE_SYSTEM,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                PaymentMethodDefinition::ENTITY_NAME => [
                    $paymentMethodId => $paymentMethod,
                ],
                ShippingMethodDefinition::ENTITY_NAME => [
                    $shippingMethodId => $shippingMethod,
                ],
                UserGroupDefinition::ENTITY_NAME => [
                    $userGroupId => $userGroup,
                ],
            ],
            'expectedException' => ChannelException::languageNotFound($anotherLanguageId),
        ];

        yield 'create base context successfully' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                PaymentMethodDefinition::ENTITY_NAME => [
                    $paymentMethodId => $paymentMethod,
                ],
                ShippingMethodDefinition::ENTITY_NAME => [
                    $shippingMethodId => $shippingMethod,
                ],
                UserGroupDefinition::ENTITY_NAME => [
                    $userGroupId => $userGroup,
                ],
                LanguageDefinition::ENTITY_NAME => [
                    Defaults::LANGUAGE_SYSTEM => $language,
                ],
            ],
            'expectedException' => null,
        ];

        yield 'create base context successfully with domain' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
                ChannelContextService::DOMAIN_ID => 'domain-id',
            ],
            'fetchDataResult' => [
                'channel_default_language_id' => Uuid::randomBytes(),
                'channel_currency_factor' => 1,
                'channel_currency_id' => Uuid::randomBytes(),
                'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
            ],
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelEntity,
                ],
                CurrencyDefinition::ENTITY_NAME => [
                    $currencyId => $currency,
                ],
                CountryDefinition::ENTITY_NAME => [
                    $countryId => $country,
                ],
                PaymentMethodDefinition::ENTITY_NAME => [
                    $paymentMethodId => $paymentMethod,
                ],
                ShippingMethodDefinition::ENTITY_NAME => [
                    $shippingMethodId => $shippingMethod,
                ],
                UserGroupDefinition::ENTITY_NAME => [
                    $userGroupId => $userGroup,
                ],
                LanguageDefinition::ENTITY_NAME => [
                    Defaults::LANGUAGE_SYSTEM => $language,
                ],
            ],
            'expectedException' => null,
        ];

        $successfulFetchDataResult = [
            'channel_default_language_id' => Uuid::randomBytes(),
            'channel_currency_factor' => 1,
            'channel_currency_id' => Uuid::randomBytes(),
            'channel_language_ids' => Defaults::LANGUAGE_SYSTEM,
        ];
        $successfulEntitySearchResult = [
            ChannelDefinition::ENTITY_NAME => [
                TestDefaults::CHANNEL => $channelEntity,
            ],
            CurrencyDefinition::ENTITY_NAME => [
                $currencyId => $currency,
            ],
            CountryDefinition::ENTITY_NAME => [
                $countryId => $country,
            ],
            PaymentMethodDefinition::ENTITY_NAME => [
                $paymentMethodId => $paymentMethod,
            ],
            ShippingMethodDefinition::ENTITY_NAME => [
                $shippingMethodId => $shippingMethod,
            ],
            UserGroupDefinition::ENTITY_NAME => [
                $userGroupId => $userGroup,
            ],
            LanguageDefinition::ENTITY_NAME => [
                Defaults::LANGUAGE_SYSTEM => $language,
            ],
        ];

        $otherCurrencyId = Uuid::randomHex();
        $otherCurrency = clone $currency;
        $otherCurrency->setUniqueIdentifier($otherCurrencyId);
        $otherCurrency->setId($otherCurrencyId);

        $channelWithOtherCurrency = clone $channelEntity;
        $channelWithOtherCurrency->setCurrencies(new CurrencyCollection([$currency, $otherCurrency]));

        yield 'create base context with currency differing from sales channel default' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $otherCurrencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
            ],
            'fetchDataResult' => $successfulFetchDataResult,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelWithOtherCurrency,
                ],
            ] + $successfulEntitySearchResult,
            'expectedException' => null,
        ];

        $channelWithoutDefaultCurrency = clone $channelWithOtherCurrency;
        $channelWithoutDefaultCurrency->setCurrencies(new CurrencyCollection([$otherCurrency]));

        yield 'default currency missing while an assigned alternative is selected' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $otherCurrencyId,
            ],
            'fetchDataResult' => $successfulFetchDataResult,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => [
                ChannelDefinition::ENTITY_NAME => [
                    TestDefaults::CHANNEL => $channelWithoutDefaultCurrency,
                ],
            ] + $successfulEntitySearchResult,
            'expectedException' => ChannelException::currencyNotFound($currencyId),
        ];

        yield 'create base context with original context' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
                ChannelContextService::ORIGINAL_CONTEXT => Context::createDefaultContext(),
            ],
            'fetchDataResult' => $successfulFetchDataResult,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => $successfulEntitySearchResult,
            'expectedException' => null,
        ];

        yield 'create base context with version id' => [
            'options' => [
                ChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                ChannelContextService::CURRENCY_ID => $currencyId,
                ChannelContextService::COUNTRY_ID => $countryId,
                ChannelContextService::VERSION_ID => Defaults::LIVE_VERSION,
            ],
            'fetchDataResult' => $successfulFetchDataResult,
            'fetchParentLanguageResult' => false,
            'entitySearchResult' => $successfulEntitySearchResult,
            'expectedException' => null,
        ];
    }
}
