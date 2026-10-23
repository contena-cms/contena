<?php declare(strict_types=1);

namespace Contena\Core;


/**
 * System-wide defaults that are fixed for performance reasons.
 *
 * @codeCoverageIgnore
 */
final class Defaults
{
    /**
     * Don't depend on this being en-GB, the underlying language can be overwritten by the installer!
     */
    public const LANGUAGE_SYSTEM = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    public const LIVE_VERSION = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    /**
     * Don't depend on this being CNY, the underlying currency can be overwritten by the installer!
     */
    public const CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca';

    public const CHANNEL_TYPE_API = 'f183ee5650cf4bdb8a774337575067a6';

    public const CHANNEL_TYPE_FRONTEND = '8a243080f92e4c719546314b577cf82b';

    public const CHANNEL_TYPE_BLOG_COMPARISON = 'ed535e5722134ac1aa6524f73e26881b';

    public const CHANNEL_TYPE_AGENTIC_COMMERCE = '5e29f9890c4d4d519a1c7f9d5c24b7c1';

    public const STORAGE_DATE_TIME_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * Do not use STORAGE_DATE_FORMAT for createdAt fields, use STORAGE_DATE_TIME_FORMAT instead
     */
    public const STORAGE_DATE_FORMAT = 'Y-m-d';

    public const MICROTIME_FORMAT = 'U.u';

    public const CMS_BLOG_DETAIL_PAGE = '7a6d253a67204037966f42b0119704d5';
}
