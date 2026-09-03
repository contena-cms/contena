<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Configuration;

use Contena\Core\System\Payment\PaymentException;

final class ChannelConfigValidator
{
    /**
     * @param array<array-key, mixed> $schema
     * @param array<string, mixed> $config
     */
    public function validate(string $channel, array $schema, array $config): void
    {
        $invalid = [];

        foreach ($schema as $card) {
            if (!\is_array($card)) {
                continue;
            }

            foreach ($card['elements'] ?? [] as $field) {
                if (!\is_array($field) || !\is_string($field['name'] ?? null)) {
                    continue;
                }

                $name = $field['name'];
                $value = $config[$name] ?? null;
                if (($field['required'] ?? false) === true && ($value === null || $value === '')) {
                    $invalid[] = $name;

                    continue;
                }
                if ($value === null || $value === '') {
                    continue;
                }

                $valid = match ($field['type'] ?? 'text') {
                    'int' => \is_int($value),
                    'float' => \is_float($value) || \is_int($value),
                    'bool', 'checkbox' => \is_bool($value),
                    'multi-select' => \is_array($value),
                    'url' => \is_string($value) && filter_var($value, \FILTER_VALIDATE_URL) !== false,
                    default => \is_string($value),
                };
                if (!$valid) {
                    $invalid[] = $name;
                }
            }
        }

        if ($invalid !== []) {
            throw $invalid
                    |> array_unique(...)
                    |> array_values(...)
                    |> (fn ($x) => PaymentException::invalidChannelConfig($channel, $x));
        }
    }
}
