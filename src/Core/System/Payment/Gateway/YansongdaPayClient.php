<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\PaymentException;
use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Artful\Contract\ShortcutInterface;
use Yansongda\Artful\Rocket;
use Yansongda\Pay\Pay;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Str;

/**
 * @internal
 */
final class YansongdaPayClient implements YansongdaPayClientInterface
{
    public function request(string $provider, array $config, string $operation, array $parameters): array
    {
        Pay::clear();

        try {
            Pay::config([$provider => ['default' => $config]], new Container());
            $gateway = Pay::get($provider);
            $callback = [$gateway, $operation];
            if (!\is_object($gateway) || !$this->supports($gateway, $provider, $operation) || !\is_callable($callback)) {
                throw PaymentException::capabilityNotSupported($provider, $operation);
            }

            return $this->normalizeResponse($callback($parameters));
        } finally {
            Pay::clear();
        }
    }

    private function supports(object $gateway, string $provider, string $operation): bool
    {
        if (method_exists($gateway, $operation)) {
            return true;
        }

        $shortcut = \sprintf(
            'Yansongda\\Pay\\Shortcut\\%s\\%sShortcut',
            Str::studly($provider),
            Str::studly($operation),
        );

        return is_subclass_of($shortcut, ShortcutInterface::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResponse(mixed $response): array
    {
        if ($response instanceof Collection) {
            return $response->all();
        }
        if ($response instanceof Rocket) {
            return $this->normalizeResponse($response->getDestination() ?? $response->getPayload());
        }
        if ($response instanceof ResponseInterface) {
            return [
                '_http_status' => $response->getStatusCode(),
                '_headers' => $response->getHeaders(),
                '_body' => (string) $response->getBody(),
            ];
        }
        if (\is_array($response)) {
            return $response;
        }

        return ['value' => $response];
    }
}
