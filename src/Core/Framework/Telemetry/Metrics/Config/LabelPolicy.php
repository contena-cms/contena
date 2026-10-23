<?php declare(strict_types=1);

namespace Contena\Core\Framework\Telemetry\Metrics\Config;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
enum LabelPolicy: string
{
    case REPLACE = 'replace';

    case DISCARD = 'discard';

    case OPEN = 'open';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case) => $case->value, self::cases());
    }
}
