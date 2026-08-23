<?php declare(strict_types=1);

namespace Contena\Core;

/**
 * System-wide defaults that are fixed for performance reasons.
 *
 * @codeCoverageIgnore
 */
final class Defaults
{
    public const string LANGUAGE_SYSTEM = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';

    public const string DEFAULT_LOCALE = 'zh-CN';

    public const string DEFAULT_TIME_ZONE = 'Asia/Shanghai';

    public const string LIVE_VERSION = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    public const string CHANNEL_TYPE_API = 'f183ee5650cf4bdb8a774337575067a6';

    public const string CHANNEL_TYPE_WEB = '8a243080f92e4c719546314b577cf82b';

    public const string STORAGE_DATE_TIME_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * Do not use STORAGE_DATE_FORMAT for createdAt fields, use STORAGE_DATE_TIME_FORMAT instead
     */
    public const string STORAGE_DATE_FORMAT = 'Y-m-d';

    public const string MICROTIME_FORMAT = 'U.u';
}
