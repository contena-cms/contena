<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Registration;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;

/**
 * @internal only for use by the app-system
 */
class PrivateHandshake implements AppHandshakeInterface
{
    public function __construct(
        private readonly string $installationUrl,
        #[\SensitiveParameter]
        private readonly string $secret,
        private readonly string $appEndpoint,
        private readonly string $appName,
        private readonly string $installationId,
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
            'installation-id' => $this->installationId,
            'installation-url' => $this->installationUrl,
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
        ]);

        $signature = hash_hmac('sha256', $uri->getQuery(), $this->secret);

        $headers = [
            'contena-app-signature' => $signature,
            'ct-version' => $this->contenaVersion,
        ];

        // Add the installation signature for re-registration.
        if ($this->currentAppSecret !== null) {
            $instanceSignature = hash_hmac('sha256', $uri->getQuery(), $this->currentAppSecret);
            $headers['contena-installation-signature'] = $instanceSignature;
        }

        return new Request(
            'GET',
            $uri,
            $headers
        );
    }

    public function fetchAppProof(): string
    {
        return hash_hmac('sha256', $this->installationId . $this->installationUrl . $this->appName, $this->secret);
    }
}
