<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

/**
 * @internal
 */
trait ConfigCastTrait
{
    /**
     * @param array<string, mixed> $config
     */
    private static function boolValue(array $config, string $key): bool
    {
        return (bool) ($config[$key] ?? false);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function intValue(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        return \is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function stringValue(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '';
    }
}
