<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Context;

use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Contena\Core\System\SalesChannel\BaseSalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelCollection;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\System\SalesChannel\SalesChannelException;
use Contena\Core\System\Tax\TaxCollection;

/**
 * @phpstan-import-type BaseContextOptions from ContextFactory
 *
 * @phpstan-type ContextOptions array{
 *     originalContext?: Context,
 *     version-id?: string,
 *     languageId?: string,
 *     currencyId?: string,
 *     countryId?: string,
 *     countryStateId?: string,
 *     paymentMethodId?: string,
 *     shippingMethodId?: string,
 *     domainId?: string,
 * }
 *
 * @internal
 */
#[Package('framework')]
class BaseSalesChannelContextFactory extends AbstractBaseSalesChannelContextFactory
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<TaxCollection> $taxRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     * @param EntityRepository<CountryStateCollection> $countryStateRepository
     * @param EntityRepository<CurrencyCountryRoundingCollection> $currencyCountryRepository
     * @param EntityRepository<EntityCollection<PartialEntity>> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $countryStateRepository,
        private readonly EntityRepository $currencyCountryRepository,
        private readonly ContextFactory $contextFactory,
        private readonly EntityRepository $languageRepository,
    ) {
    }

    /**
     * @param ContextOptions $options
     */
    public function create(string $salesChannelId, array $options = []): BaseSalesChannelContext
    {
        $context = $this->contextFactory->getContext($salesChannelId, $this->getBaseContextOptions($options));

        $criteria = new Criteria([$salesChannelId]);
        $criteria->setTitle('base-context-factory::sales-channel');
        if (!Feature::isActive('v6.8.0.0')) {
            $criteria->getAssociation('languages')
                ->addFilter(new EqualsFilter('id', $context->getLanguageId()))
                ->addAssociation('translationCode')
                ->addAssociation('locale');
        }
        $criteria->addAssociation('currencies');
        $criteria->addAssociation('domains');

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->get($salesChannelId);
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        $currencyId = $salesChannel->getCurrencyId();
        if (\array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
            $currencyId = $options[SalesChannelContextService::CURRENCY_ID];
            if (!\is_string($currencyId) || !Uuid::isValid($currencyId)) {
                throw SalesChannelException::invalidCurrencyId();
            }
        }

        $availableCurrencies = $salesChannel->getCurrencies();
        if ($availableCurrencies === null) {
            throw SalesChannelException::currencyNotFound($currencyId);
        }

        $currency = $availableCurrencies->get($currencyId);
        if ($currency === null) {
            throw SalesChannelException::currencyNotFound($currencyId);
        }
        $defaultCurrency = $availableCurrencies->get($salesChannel->getCurrencyId());
        if ($defaultCurrency === null) {
            throw SalesChannelException::currencyNotFound($salesChannel->getCurrencyId());
        }
        $salesChannel->setCurrency($defaultCurrency);

        // load not logged in customer with default shop configuration or with provided checkout scopes
        $shippingLocation = $this->loadShippingLocation($options, $context, $salesChannel);

        $groupId = $salesChannel->getCustomerGroupId();

        $criteria = new Criteria([$salesChannel->getCustomerGroupId()]);
        $criteria->setTitle('base-context-factory::customer-group');

        $customerGroup = $this->customerGroupRepository->search($criteria, $context)->getEntities()->get($groupId);
        if ($customerGroup === null) {
            throw SalesChannelException::customerGroupNotFound($groupId);
        }
        $salesChannel->setCustomerGroup($customerGroup);

        // loads tax rules based on active customer and delivery address
        $taxRules = $this->getTaxRules($context);

        // detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel);

        // detect active delivery method, at first checkout scope, at least shop default method
        $shippingMethod = $this->getShippingMethod($options, $context, $salesChannel);

        [$itemRounding, $totalRounding] = $this->getCashRounding($currency, $shippingLocation, $context);

        $context = new Context(
            $context->getSource(),
            [],
            $currency->getId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $currency->getFactor(),
            true,
            CartPrice::TAX_STATE_GROSS,
            $itemRounding
        );

        if (!Feature::isActive('v6.8.0.0')) {
            $languageInfo = $this->getLanguageInfoDeprecated($salesChannel->getLanguages(), $context->getLanguageId());
        } else {
            $languageInfo = $this->getLanguageInfo($context);
        }

        $domainId = \is_string($options[SalesChannelContextService::DOMAIN_ID] ?? null) ? $options[SalesChannelContextService::DOMAIN_ID] : null;

        return new BaseSalesChannelContext(
            $context,
            $salesChannel,
            $currency,
            $customerGroup,
            $taxRules,
            $payment,
            $shippingMethod,
            $shippingLocation,
            $itemRounding,
            $totalRounding,
            $languageInfo,
            $this->getMeasurementSystemInfo($salesChannel, $domainId),
        );
    }

    private function getTaxRules(Context $context): TaxCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('base-context-factory::taxes');
        $criteria->addAssociation('rules.type');

        return $this->taxRepository->search($criteria, $context)->getEntities();
    }





    private function getLanguageInfo(Context $context): LanguageInfo
    {
        $currentLanguageId = $context->getLanguageId();
        $criteria = (new Criteria([$currentLanguageId]))->addFields([
            'name',
            'translationCode.code',
            'locale.code',
        ]);

        $currentLanguage = $this->languageRepository->search($criteria, $context)->getEntities()->get($currentLanguageId);
        if (!$currentLanguage instanceof PartialEntity) {
            throw SalesChannelException::languageNotFound($currentLanguageId);
        }

        $locale = $currentLanguage->get('translationCode') ?? $currentLanguage->get('locale');
        \assert($locale instanceof PartialEntity, 'At least the localeId is required, so the fallback should never be null');

        return new LanguageInfo(
            $currentLanguage->get('name'),
            $locale->get('code'),
        );
    }

    private function getLanguageInfoDeprecated(?LanguageCollection $languages, string $currentLanguageId): LanguageInfo
    {
        $currentLanguage = $languages?->get($currentLanguageId);
        if ($currentLanguage === null) {
            throw SalesChannelException::languageNotFound($currentLanguageId);
        }

        $locale = $currentLanguage->getTranslationCode() ?? $currentLanguage->getLocale();
        \assert($locale !== null, 'At least the localeId is required, so the fallback should never be null');

        return new LanguageInfo(
            $currentLanguage->getTranslation('name') ?? $currentLanguage->getName(),
            $locale->getCode(),
        );
    }

    /**
     * @description load active sales channel domain's measurement units, fallback to sales channel measurement units
     */
    private function getMeasurementSystemInfo(SalesChannelEntity $salesChannelEntity, ?string $domainId): MeasurementUnits
    {
        if ($domainId && $salesChannelEntity->getDomains()?->get($domainId) instanceof SalesChannelDomainEntity) {
            return $salesChannelEntity->getDomains()->get($domainId)->getMeasurementUnits();
        }

        return $salesChannelEntity->getMeasurementUnits();
    }

    /**
     * @param ContextOptions $options
     *
     * @return BaseContextOptions
     */
    private function getBaseContextOptions(array $options): array
    {
        $contextOptions = [];
        if (\array_key_exists(SalesChannelContextService::ORIGINAL_CONTEXT, $options)) {
            $contextOptions[SalesChannelContextService::ORIGINAL_CONTEXT] = $options[SalesChannelContextService::ORIGINAL_CONTEXT];
        }
        if (\array_key_exists(SalesChannelContextService::VERSION_ID, $options)) {
            $contextOptions[SalesChannelContextService::VERSION_ID] = $options[SalesChannelContextService::VERSION_ID];
        }
        if (\array_key_exists(SalesChannelContextService::LANGUAGE_ID, $options)) {
            $contextOptions[SalesChannelContextService::LANGUAGE_ID] = $options[SalesChannelContextService::LANGUAGE_ID];
        }

        return $contextOptions;
    }
}
