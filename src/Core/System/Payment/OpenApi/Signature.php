<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi;

final class Signature
{
    final public const int TIMESTAMP_TOLERANCE = 300;

    /**
     * @param array<string, mixed> $parameters
     */
    public static function sign(array $parameters, string $secret): string
    {
        unset($parameters['sign']);
        ksort($parameters);

        $parts = [];
        foreach ($parameters as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (\is_array($value)) {
                $value = json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            }
            if (!\is_scalar($value)) {
                throw OpenApiException::invalidRequest('Request signature parameters must contain scalar or array values.');
            }

            $parts[] = $name . '=' . (string) $value;
        }

        return hash_hmac('sha256', implode('&', $parts) . '&key=' . $secret, $secret);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function verify(array $parameters, string $secret, int $now): bool
    {
        $signature = $parameters['sign'] ?? null;
        $timestamp = $parameters['timestamp'] ?? null;
        if (!\is_string($signature) || $signature === '' || !\is_numeric($timestamp)) {
            return false;
        }
        if (abs($now - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        try {
            return hash_equals(self::sign($parameters, $secret), $signature);
        } catch (\JsonException|OpenApiException) {
            return false;
        }
    }
}
