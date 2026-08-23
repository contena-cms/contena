<?php declare(strict_types=1);

namespace Contena\Core;

final class PlatformRequest
{
    /**
     * Response Headers
     */
    public const string HEADER_FRAME_OPTIONS = 'x-frame-options';

    /**
     * Context headers
     */
    public const string HEADER_CONTEXT_TOKEN = 'ct-context-token';
    public const string HEADER_TENANT_ID = 'ct-tenant-id';
    public const string HEADER_ACCESS_KEY = 'ct-access-key';
    public const string HEADER_DOMAIN = 'ct-domain';
    public const string HEADER_LANGUAGE_ID = 'ct-language-id';
    public const string HEADER_INHERITANCE = 'ct-inheritance';
    public const string HEADER_VERSION_ID = 'ct-version-id';
    public const string HEADER_INCLUDE_SEO_URLS = 'ct-include-seo-urls';
    public const string HEADER_INCLUDE_SEARCH_INFO = 'ct-include-search-info';
    public const string HEADER_SKIP_TRIGGER_FLOW = 'ct-skip-trigger-flow';
    public const string HEADER_INDEXING_BEHAVIOR = 'indexing-behavior';
    public const string HEADER_INDEXING_SKIP = 'indexing-skip';
    public const string HEADER_INDEXING_ONLY = 'indexing-only';
    public const string HEADER_FORCE_CACHE_INVALIDATE = 'ct-force-cache-invalidate';

    /**
     * MCP Streamable HTTP transport headers.
     */
    public const string HEADER_MCP_SESSION_ID = 'mcp-session-id';
    public const string HEADER_MCP_PROTOCOL_VERSION = 'mcp-protocol-version';

    /**
     * API Expectation headers to check requirements are fulfilled
     */
    public const string HEADER_EXPECT_PACKAGES = 'ct-expect-packages';

    /**
     * Context attributes
     */
    public const string ATTRIBUTE_CONTEXT_OBJECT = 'ct-context';
    public const string ATTRIBUTE_CHANNEL_CONTEXT_OBJECT = 'ct-channel-context';
    public const string ATTRIBUTE_CHANNEL_ID = 'contena-channel-id';

    public const string ATTRIBUTE_RESOLVED_TENANT_ID = 'contena-resolved-tenant-id';
    public const string ATTRIBUTE_IMITATING_USER_ID = 'ct-imitating-user-id';
    public const string ATTRIBUTE_MAINTENANCE = 'contena-maintenance';
    public const string ATTRIBUTE_MAINTENANCE_IP_ALLOWLIST = 'contena-maintenance-ip-allowlist';

    public const string ATTRIBUTE_ACL = '_acl';
    public const string ATTRIBUTE_CAPTCHA = '_captcha';
    public const string ATTRIBUTE_ROUTE_SCOPE = '_routeScope';
    public const string ATTRIBUTE_ENTITY = '_entity';
    public const string ATTRIBUTE_OPENAPI = '_openapi';
    public const string ATTRIBUTE_NO_STORE = '_noStore';
    public const string ATTRIBUTE_HTTP_CACHE = '_httpCache';
    public const string ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE = 'allow_maintenance';
    public const string ATTRIBUTE_CONTEXT_TOKEN_REQUIRED = '_contextTokenRequired';
    public const string ATTRIBUTE_LOGIN_REQUIRED = '_loginRequired';
    public const string ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST = '_loginRequiredAllowGuest';

    public const string ATTRIBUTE_CLEAR_SITE_DATA = '_clearSiteData';

    public const array ATTRIBUTE_INTERNAL_ROUTE_PARAMS = [
        self::ATTRIBUTE_CAPTCHA,
        self::ATTRIBUTE_ROUTE_SCOPE,
        self::ATTRIBUTE_ENTITY,
        self::ATTRIBUTE_OPENAPI,
        self::ATTRIBUTE_NO_STORE,
        self::ATTRIBUTE_HTTP_CACHE,
        self::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED,
        self::ATTRIBUTE_LOGIN_REQUIRED,
        self::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST,
        self::ATTRIBUTE_IS_ALLOWED_IN_MAINTENANCE,
    ];

    /**
     * CSP
     */
    public const string ATTRIBUTE_CSP_NONCE = '_cspNonce';

    /**
     * OAuth attributes
     */
    public const string ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID = 'oauth_access_token_id';
    public const string ATTRIBUTE_OAUTH_CLIENT_ID = 'oauth_client_id';
    public const string ATTRIBUTE_OAUTH_USER_ID = 'oauth_user_id';
    public const string ATTRIBUTE_OAUTH_SCOPES = 'oauth_scopes';
    public const string ATTRIBUTE_OAUTH_PRE_AUTHENTICATED = 'oauth_pre_authenticated';

    public const string FALLBACK_SESSION_NAME = 'session-';

    private function __construct()
    {
    }
}
