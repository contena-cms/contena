<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Store\Services\StoreClient;
use GuzzleHttp\Exception\ClientException;

/**
 * @internal only for use by the app-system
 */
class StoreHandshake implements AppHandshakeInterface
{
    private const SBP_EXCEPTION_UNAUTHORIZED = 'ContenaPlatformException-1';

    private const SBP_EXCEPTION_NO_LICENSE = 'ContenaPlatformException-16';

    public function __construct(
        private readonly string $shopUrl,
        private readonly string $appEndpoint,
        private readonly string $appName,
        private readonly string $installationId,
        private readonly StoreClient $storeClient,
        private readonly string $contenaVersion,
        private readonly ClockInterface $clock,
        #[\SensitiveParameter]
        private readonly ?string $currentAppSecret = null
    ) {
    }

    public function assembleRequest(): RequestInterface
    {
        $uri = new Uri($this->appEndpoint);

        $uri = Uri::withQueryValues($uri, [
            'shop-id' => $this->installationId,
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
        ]);

        $signature = $this->signPayload($uri->getQuery());

        $headers = [
            'contena-app-signature' => $signature,
            'sw-version' => $this->contenaVersion,
        ];

        // Add shop signature for re-registration (secret rotation)
        if ($this->currentAppSecret !== null) {
            $shopSignature = hash_hmac('sha256', $uri->getQuery(), $this->currentAppSecret);
            $headers['contena-shop-signature'] = $shopSignature;
        }

        return new Request(
            'GET',
            $uri,
            $headers
        );
    }

    public function fetchAppProof(): string
    {
        $proof = $this->installationId . $this->shopUrl . $this->appName;

        return $this->storeClient->signPayloadWithAppSecret($proof, $this->appName);
    }

    private function signPayload(string $payload): string
    {
        try {
            return $this->storeClient->signPayloadWithAppSecret($payload, $this->appName);
        } catch (\Exception $e) {
            if ($e instanceof ClientException) {
                $response = \json_decode($e->getResponse()->getBody()->getContents(), true, flags: \JSON_THROW_ON_ERROR);

                if ($response['code'] === self::SBP_EXCEPTION_UNAUTHORIZED || $response['code'] === self::SBP_EXCEPTION_NO_LICENSE) {
                    throw AppException::licenseCouldNotBeVerified($this->appName, $e);
                }
            }

            throw AppException::registrationFailed(
                $this->appName,
                'Could not sign payload with store secret',
                $e
            );
        }
    }
}
