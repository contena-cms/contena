<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\SystemConfig\Channel\ConfigCastTrait;

/**
 * Cart and checkout settings (core.cart).
 *
 * @codeCoverageIgnore
 */
final class ShopCartSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly int $maxQuantity,
        public readonly int $lineItemAddLimit,
        public readonly bool $showDeliveryTime,
        public readonly bool $showSubtotal,
        public readonly bool $columnTaxInsteadUnitPrice,
        public readonly bool $showUserComment,
        public readonly bool $showTosCheckbox,
        public readonly bool $openOffcanvasAfterAddToCart,
        public readonly bool $wishlistEnabled,
        public readonly bool $logoutGuestAfterCheckout,
        public readonly bool $enableOrderRefunds,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.cart config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            maxQuantity: self::intValue($config, 'maxQuantity'),
            lineItemAddLimit: self::intValue($config, 'lineItemAddLimit'),
            showDeliveryTime: self::boolValue($config, 'showDeliveryTime'),
            showSubtotal: self::boolValue($config, 'showSubtotal'),
            columnTaxInsteadUnitPrice: self::boolValue($config, 'columnTaxInsteadUnitPrice'),
            showUserComment: self::boolValue($config, 'showUserComment'),
            showTosCheckbox: self::boolValue($config, 'showTosCheckbox'),
            openOffcanvasAfterAddToCart: self::boolValue($config, 'openOffcanvasAfterAddToCart'),
            wishlistEnabled: self::boolValue($config, 'wishlistEnabled'),
            logoutGuestAfterCheckout: self::boolValue($config, 'logoutGuestAfterCheckout'),
            enableOrderRefunds: self::boolValue($config, 'enableOrderRefunds'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_cart';
    }
}
