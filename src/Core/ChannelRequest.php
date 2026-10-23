<?php declare(strict_types=1);

namespace Contena\Core;

use Contena\Core\Framework\Log\Package;

final class ChannelRequest
{
    public const ATTRIBUTE_IS_CHANNEL_REQUEST = '_is_channel';

    public const ATTRIBUTE_THEME_ID = 'theme-id';
    public const ATTRIBUTE_THEME_NAME = 'theme-name';
    public const ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    public const ATTRIBUTE_CHANNEL_MAINTENANCE = 'sw-maintenance';

    public const ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST = 'sw-maintenance-ip-allowlist';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST instead.
     */
    public const ATTRIBUTE_CHANNEL_MAINTENANCE_IP_WHITLELIST = 'sw-maintenance-ip-whitelist';

    /**
     * domain-resolved attributes
     */
    public const ATTRIBUTE_DOMAIN_ID = 'sw-domain-id';
    public const ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'sw-snippet-set-id';
    public const ATTRIBUTE_DOMAIN_CURRENCY_ID = 'sw-currency-id';

    public const ATTRIBUTE_CANONICAL_LINK = 'sw-canonical-link';

    public const ATTRIBUTE_FRONTEND_URL = 'sw-frontend-url';

    private function __construct()
    {
    }
}
