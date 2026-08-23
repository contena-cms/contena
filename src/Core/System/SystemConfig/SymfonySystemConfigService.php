<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

/**
 * @internal
 */
readonly class SymfonySystemConfigService
{
    /**
     * @param array<string, array<mixed>> $configuration
     */
    public function __construct(private array $configuration)
    {
    }

    /**
     * @return array<mixed>
     */
    public function getConfig(?string $channelId = null): array
    {
        return $this->configuration[$channelId ?? 'default'] ?? [];
    }

    public function get(string $configKey, ?string $channelId = null): mixed
    {
        if ($channelId !== null) {
            $channelConfiguration = $this->configuration[$channelId] ?? [];

            if (\array_key_exists($configKey, $channelConfiguration)) {
                return $channelConfiguration[$configKey];
            }
        }

        $defaultConfiguration = $this->configuration['default'] ?? [];

        return $defaultConfiguration[$configKey] ?? null;
    }

    public function has(string $configKey): bool
    {
        foreach ($this->configuration as $channelConfiguration) {
            if (\array_key_exists($configKey, $channelConfiguration)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $merged
     *
     * @return array<mixed>
     */
    public function override(array $merged, ?string $channelId, bool $inherit = true, bool $nesting = true): array
    {
        $values = [$this->getConfig($channelId)];

        if ($channelId !== null && $inherit) {
            array_unshift($values, $this->getConfig());
        }

        $specific = array_merge(...$values);

        if (!$nesting) {
            return array_replace_recursive($merged, $specific);
        }

        foreach ($specific as $key => $value) {
            $keys = \explode('.', (string) $key);

            if (\count($keys) === 1) {
                $merged[$key] = $value;

                continue;
            }

            $specific = $this->getSubArray($specific, $keys, $value);

            unset($specific[$key]);
        }

        return array_replace_recursive($merged, $specific);
    }

    /**
     * @param array<mixed> $configValues
     * @param array<string> $keys
     * @param array<mixed>|bool|float|int|string|null $value
     *
     * @return array<mixed>
     */
    private function getSubArray(array $configValues, array $keys, mixed $value): array
    {
        $key = \array_shift($keys);

        if ($key === null) {
            return $configValues;
        }

        if ($keys === []) {
            // Configs can be overwritten with channel_id.
            $inheritedValuePresent = \array_key_exists($key, $configValues);
            $valueConsideredEmpty = \in_array($value, [null, '', '0', 0, 0.0, []], true);

            if ($inheritedValuePresent && $valueConsideredEmpty) {
                return $configValues;
            }

            $configValues[$key] = $value;
        } else {
            if (!\array_key_exists($key, $configValues)) {
                $configValues[$key] = [];
            }

            $configValues[$key] = $this->getSubArray($configValues[$key], $keys, $value);
        }

        return $configValues;
    }
}
