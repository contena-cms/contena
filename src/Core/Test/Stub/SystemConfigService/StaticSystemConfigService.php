<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\SystemConfigService;

use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @final
 */
class StaticSystemConfigService extends SystemConfigService
{
    /**
     * @param array<string, mixed>|array<string, array<string, mixed>> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function get(string $key, ?string $channelId = null, ?Context $context = null)
    {
        if ($channelId) {
            return $this->lookupValue($this->config[$channelId] ?? $this->config, $key);
        }

        return $this->lookupValue($this->config, $key);
    }

    public function set(string $key, $value, ?string $channelId = null, bool $silent = true, ?Context $context = null): void
    {
        if ($channelId) {
            $this->config[$channelId][$key] = $value;

            return;
        }

        $this->config[$key] = $value;
    }

    public function setMultiple(array $values, ?string $channelId = null, bool $silent = true, ?Context $context = null): void
    {
        foreach ($values as $k => $v) {
            $this->set($k, $v, $channelId, $silent, $context);
        }
    }

    /**
     * @param array<string, mixed> $param
     */
    private function lookupValue(array $param, string $key): mixed
    {
        if (\array_key_exists($key, $param)) {
            return $param[$key];
        }

        // Look for sub keys
        $foundValues = [];
        $prefix = rtrim($key, '.');
        foreach ($param as $configKey => $configValue) {
            if (!str_starts_with($configKey, $prefix)) {
                continue;
            }

            $formattedKey = substr($configKey, \strlen($prefix) + 1);

            $pointer = &$foundValues;
            foreach (explode('.', $formattedKey) as $part) {
                if (!\array_key_exists($part, $pointer)) {
                    $pointer[$part] = [];
                }

                $pointer = &$pointer[$part];
            }
            $pointer = $configValue;
        }

        if ($foundValues === []) {
            return null;
        }

        return $foundValues;
    }
}
