<?php declare(strict_types=1);

namespace Contena\Core\Test;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Channel\Context\LanguageInfo;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class Generator extends TestCase
{
    final public const TOKEN = 'test-token';
    final public const DOMAIN = 'test-domain';
    final public const NAVIGATION_CATEGORY = 'f8466865cc6a45e48ed98dd2f6a0a293';
    final public const TAX_CALCULATION_TYPE = ChannelDefinition::CALCULATION_TYPE_HORIZONTAL;
    final public const CUSTOMER_GROUP_DISPLAY_GROSS = true;
    final public const TAX = 'c725e107825c4c7281673aeea66ed67e';
    final public const TAX_RATE = 19.0;
    final public const PAYMENT_METHOD = 'cce0e1ca23de4c55868ce057f628c349';
    final public const SHIPPING_METHOD = '37dbe80c5cbb4852a97cb742ed04ba41';
    final public const COUNTRY = 'd4eb3205dd9444169b3f60c056c313a1';
    final public const COUNTRY_STATE = '119d6e30fc4f468daa88ff5b413e9322';
    final public const CUSTOMER_ADDRESS = '08f1594313494c3e9eb57bb53486fe61';
    final public const CUSTOMER = '42d58aa78cf14851968a786a66bab93a';
    final public const LANGUAGE_INFO_NAME = 'English';
    final public const LANGUAGE_INFO_LOCALE_CODE = 'en-GB';

    /**
     * @param array<string, string[]> $areaRuleIds
     * @param array<array-key, mixed> $overrides
     */
    public static function generateChannelContext(
        ?Context $baseContext = null,
        ?string $token = null,
        ?string $domainId = null,
        ?ChannelEntity $channel = null,
        ?CurrencyEntity $currency = null,
        ?UserGroupEntity $currentUserGroup = null,
        ?TaxCollection $taxRules = null,
        ?UserEntity $user = null,
        ?CashRoundingConfig $itemRounding = null,
        ?CashRoundingConfig $totalRounding = null,
        ?array $areaRuleIds = [],
        ?LanguageInfo $languageInfo = null,
        ?CountryEntity $country = null,
        ?CountryStateEntity $countryState = null,
        ?UserAddressEntity $userAddress = null,
        ?array $overrides = [],
    ): ChannelContext {
        $baseContext ??= Context::createDefaultContext();

        $token ??= self::TOKEN;

        $domainId ??= self::DOMAIN;

        if (!$channel) {
            $channel = new ChannelEntity();
            $channel->setId(TestDefaults::CHANNEL);
            $channel->setNavigationCategoryId(self::NAVIGATION_CATEGORY);
            $channel->setTaxCalculationType(self::TAX_CALCULATION_TYPE);
            $channel->setNavigationCategoryDepth(2);
            $channel->setTypeId(Defaults::CHANNEL_TYPE_FRONTEND);
        }

        if (!$currency) {
            $currency = new CurrencyEntity();
            $currency->setId($baseContext->getCurrencyId());
            $currency->setFactor($baseContext->getCurrencyFactor());
        }

        if (!$currentUserGroup) {
            $currentUserGroup = new UserGroupEntity();
            $currentUserGroup->setId(TestDefaults::FALLBACK_CUSTOMER_GROUP);
            $currentUserGroup->setDisplayGross(self::CUSTOMER_GROUP_DISPLAY_GROSS);
        }

        if (!$taxRules) {
            $tax = new TaxEntity();
            $tax->setId(self::TAX);
            $tax->setTaxRate(self::TAX_RATE);

            $taxRules = new TaxCollection([$tax]);
        }

        if (!$user) {
            $user = new UserEntity();
            $user->setId(self::CUSTOMER);
            $user->setGroupId($currentUserGroup->getId());
            $user->setGroup($currentUserGroup);
            $user->setChannelId($channel->getId());
            $user->setChannel($channel);
            $user->setGuest(false);
        }

        $itemRounding ??= clone $baseContext->getRounding();

        $totalRounding ??= clone $baseContext->getRounding();

        $areaRuleIds ??= [];

        $languageInfo ??= self::createLanguageInfo();

        $channelContext = new ChannelContext(
            $baseContext,
            $token,
            $domainId,
            $channel,
            $currency,
            $currentUserGroup,
            $taxRules,
            $user,
            $itemRounding,
            $totalRounding,
            $languageInfo,
            $areaRuleIds,
        );

        if ($overrides) {
            $channelContext->assign($overrides);
        }

        return $channelContext;
    }

    public static function createLanguageInfo(
        ?string $id = null,
        ?string $name = null,
    ): LanguageInfo {
        return new LanguageInfo(
            $id ?? Defaults::LANGUAGE_SYSTEM,
            $name ?? self::LANGUAGE_INFO_NAME,
        );
    }
}
