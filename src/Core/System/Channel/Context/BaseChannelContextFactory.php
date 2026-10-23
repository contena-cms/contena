<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\BaseChannelContext;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;

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
class BaseChannelContextFactory extends AbstractBaseChannelContextFactory
{
    /**
     * @param EntityRepository<ChannelCollection> $channelRepository
     * @param EntityRepository<UserGroupCollection> $userGroupRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<TaxCollection> $taxRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     * @param EntityRepository<CountryStateCollection> $countryStateRepository
     * @param EntityRepository<CurrencyCountryRoundingCollection> $currencyCountryRepository
     * @param EntityRepository<EntityCollection<PartialEntity>> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $channelRepository,
        private readonly EntityRepository $userGroupRepository,
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
    public function create(string $channelId, array $options = []): BaseChannelContext
    {
        $context = $this->contextFactory->getContext($channelId, $this->getBaseContextOptions($options));

        $criteria = new Criteria([$channelId]);
        $criteria->setTitle('base-context-factory::sales-channel');
        if (!Feature::isActive('v6.8.0.0')) {
            $criteria->getAssociation('languages')
                ->addFilter(new EqualsFilter('id', $context->getLanguageId()))
                ->addAssociation('translationCode')
                ->addAssociation('locale');
        }
        $criteria->addAssociation('currencies');
        $criteria->addAssociation('domains');

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->get($channelId);
        if (!$channel instanceof ChannelEntity) {
            throw ChannelException::channelNotFound($channelId);
        }

        $currencyId = $channel->getCurrencyId();
        if (\array_key_exists(ChannelContextService::CURRENCY_ID, $options)) {
            $currencyId = $options[ChannelContextService::CURRENCY_ID];
            if (!\is_string($currencyId) || !Uuid::isValid($currencyId)) {
                throw ChannelException::invalidCurrencyId();
            }
        }

        $availableCurrencies = $channel->getCurrencies();
        if ($availableCurrencies === null) {
            throw ChannelException::currencyNotFound($currencyId);
        }

        $currency = $availableCurrencies->get($currencyId);
        if ($currency === null) {
            throw ChannelException::currencyNotFound($currencyId);
        }
        $defaultCurrency = $availableCurrencies->get($channel->getCurrencyId());
        if ($defaultCurrency === null) {
            throw ChannelException::currencyNotFound($channel->getCurrencyId());
        }
        $channel->setCurrency($defaultCurrency);

        // load not logged in user with default shop configuration or with provided checkout scopes
        $shippingLocation = $this->loadShippingLocation($options, $context, $channel);

        $groupId = $channel->getUserGroupId();

        $criteria = new Criteria([$channel->getUserGroupId()]);
        $criteria->setTitle('base-context-factory::user-group');

        $userGroup = $this->userGroupRepository->search($criteria, $context)->getEntities()->get($groupId);
        if ($userGroup === null) {
            throw ChannelException::userGroupNotFound($groupId);
        }
        $channel->setUserGroup($userGroup);

        // loads tax rules based on active user and delivery address
        $taxRules = $this->getTaxRules($context);

        // detect active payment method, first check if checkout defined other payment method, otherwise validate if user logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $channel);

        // detect active delivery method, at first checkout scope, at least shop default method
        $shippingMethod = $this->getShippingMethod($options, $context, $channel);

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
            $languageInfo = $this->getLanguageInfoDeprecated($channel->getLanguages(), $context->getLanguageId());
        } else {
            $languageInfo = $this->getLanguageInfo($context);
        }

        $domainId = \is_string($options[ChannelContextService::DOMAIN_ID] ?? null) ? $options[ChannelContextService::DOMAIN_ID] : null;

        return new BaseChannelContext(
            $context,
            $channel,
            $currency,
            $userGroup,
            $taxRules,
            $payment,
            $shippingMethod,
            $shippingLocation,
            $itemRounding,
            $totalRounding,
            $languageInfo,
            $this->getMeasurementSystemInfo($channel, $domainId),
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
            throw ChannelException::languageNotFound($currentLanguageId);
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
            throw ChannelException::languageNotFound($currentLanguageId);
        }

        $locale = $currentLanguage->getTranslationCode() ?? $currentLanguage->getLocale();
        \assert($locale !== null, 'At least the localeId is required, so the fallback should never be null');

        return new LanguageInfo(
            $currentLanguage->getTranslation('name') ?? $currentLanguage->getName(),
            $locale->getCode(),
        );
    }


    /**
     * @param ContextOptions $options
     *
     * @return BaseContextOptions
     */
    private function getBaseContextOptions(array $options): array
    {
        $contextOptions = [];
        if (\array_key_exists(ChannelContextService::ORIGINAL_CONTEXT, $options)) {
            $contextOptions[ChannelContextService::ORIGINAL_CONTEXT] = $options[ChannelContextService::ORIGINAL_CONTEXT];
        }
        if (\array_key_exists(ChannelContextService::VERSION_ID, $options)) {
            $contextOptions[ChannelContextService::VERSION_ID] = $options[ChannelContextService::VERSION_ID];
        }
        if (\array_key_exists(ChannelContextService::LANGUAGE_ID, $options)) {
            $contextOptions[ChannelContextService::LANGUAGE_ID] = $options[ChannelContextService::LANGUAGE_ID];
        }

        return $contextOptions;
    }
}
