<?php declare(strict_types=1);

namespace Contena\Core\Test;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
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
        ?PaymentMethodEntity $paymentMethod = null,
        ?ShippingMethodEntity $shippingMethod = null,
        ?ShippingLocation $shippingLocation = null,
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

        if (!$paymentMethod) {
            $paymentMethod = new PaymentMethodEntity();
            $paymentMethod->setId(self::PAYMENT_METHOD);
            $paymentMethod->setHandlerIdentifier(DefaultPayment::class);
            $paymentMethod->setName('Generated Payment');
            $paymentMethod->setActive(true);
        }

        $channel->setPaymentMethodIds([$paymentMethod->getId()]);
        $channel->setPaymentMethodId($paymentMethod->getId());
        $channel->setPaymentMethod($paymentMethod);

        if (!$shippingMethod) {
            $shippingMethod = new ShippingMethodEntity();
            $shippingMethod->setId(self::SHIPPING_METHOD);
            $shippingMethod->setName('Generated Shipping');
            $shippingMethod->setActive(true);
        }

        $channel->setShippingMethodId($shippingMethod->getId());
        $channel->setShippingMethod($shippingMethod);

        if (!$shippingLocation) {
            if (!$country) {
                $country = new CountryEntity();
                $country->setId(self::COUNTRY);
            }

            if (!$countryState) {
                $countryState = new CountryStateEntity();
                $countryState->setId(self::COUNTRY_STATE);
                $countryState->setCountryId($country->getId());
                $countryState->setCountry($country);
            }

            if (!$userAddress) {
                $userAddress = new UserAddressEntity();
                $userAddress->setId(self::CUSTOMER_ADDRESS);
            }

            $userAddress->setCountryId($country->getId());
            $userAddress->setCountry($country);
            $userAddress->setCountryStateId($countryState->getId());
            $userAddress->setCountryState($countryState);

            $shippingLocation = ShippingLocation::createFromAddress($userAddress);
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
            $paymentMethod,
            $shippingMethod,
            $shippingLocation,
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

    public static function createCart(): Cart
    {
        $cart = new Cart('test');
        $cart->setLineItems(
            new LineItemCollection([
                (new LineItem('A', 'blog', 'A', 27))
                    ->setPrice(new CalculatedPrice(10, 270, new CalculatedTaxCollection(), new TaxRuleCollection(), 27)),
                (new LineItem('B', 'test', 'B', 5))
                    ->setGood(false)
                    ->setPrice(new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())),
            ])
        );
        $cart->setPrice(
            new CartPrice(
                275.0,
                275.0,
                0,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            )
        );

        return $cart;
    }

    public static function createCartWithDelivery(): Cart
    {
        $cart = static::createCart();

        $shippingMethod = new ShippingMethodEntity();
        $calculatedPrice = new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection());
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $deliveryPositionCollection = new DeliveryPositionCollection();
        foreach ($cart->getLineItems() as $lineItem) {
            $deliveryPosition = new DeliveryPosition(
                'anyIdentifier',
                $lineItem,
                $lineItem->getQuantity(),
                $calculatedPrice,
                $deliveryDate
            );

            $lineItem->setDeliveryInformation(new DeliveryInformation(1000, 10.0, false, 2, null, 10.0, 10.0, 10.0));

            $deliveryPositionCollection->add($deliveryPosition);
        }

        $delivery = new Delivery(
            $deliveryPositionCollection,
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            $calculatedPrice
        );

        $cart->addDeliveries(new DeliveryCollection([$delivery]));

        return $cart;
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
