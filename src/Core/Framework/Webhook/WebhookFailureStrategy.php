<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;


/**
 * @codeCoverageIgnore
 *
 * @internal
 */
enum WebhookFailureStrategy: string
{
    case DisableOnThreshold = 'disable_on_threshold';
    case Ignore = 'ignore';
    public const MAX_ERROR_COUNT = 10;

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
