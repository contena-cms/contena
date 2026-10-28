<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('framework')]
class ServiceClient
{
    public function __construct(
        public readonly HttpClientInterface $client,
        private readonly string $contenaVersion,
        private readonly ServiceEntry $entry
    ) {
    }

    public function latestAppInfo(): AppInfo
    {
        $response = $this->client->request('GET', $this->entry->appEndpoint, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'query' => [
                'contenaVersion' => $this->contenaVersion,
            ],
        ]);

        $this->checkResponse($response);

        return AppInfo::fromRegistryResponse($this->entry->name, $response->toArray());
    }

    private function checkResponse(ResponseInterface $response): void
    {
        try {
            if ($response->getStatusCode() !== 200) {
                throw ServiceException::requestFailed($response);
            }
        } catch (TransportExceptionInterface $exception) {
            throw ServiceException::requestTransportError($exception);
        }
    }
}
