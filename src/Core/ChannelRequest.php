<?php declare(strict_types=1);

namespace Contena\Core;

final class ChannelRequest
{
    public const string ATTRIBUTE_IS_CHANNEL_REQUEST = '_is_channel';

    public const string ATTRIBUTE_THEME_ID = 'theme-id';
    public const string ATTRIBUTE_THEME_NAME = 'theme-name';
    public const string ATTRIBUTE_THEME_BASE_NAME = 'theme-base-name';

    public const string ATTRIBUTE_CHANNEL_MAINTENANCE = 'contena-channel-maintenance';
    public const string ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST = 'contena-channel-maintenance-ip-allowlist';

    /**
     * Domain-resolved attributes.
     */
    public const string ATTRIBUTE_DOMAIN_ID = 'contena-channel-domain-id';
    public const string ATTRIBUTE_DOMAIN_LOCALE = '_locale';
    public const string ATTRIBUTE_DOMAIN_SNIPPET_SET_ID = 'contena-channel-snippet-set-id';

    public const string ATTRIBUTE_CANONICAL_LINK = 'contena-canonical-link';

    public const string ATTRIBUTE_FRONTEND_URL = 'contena-frontend-url';

    private function __construct()
    {
    }
}
