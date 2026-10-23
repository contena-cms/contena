<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Service\ServiceRegistry\Client as ServiceRegistryClient;
use Contena\Core\Service\ServiceRegistry\ServiceEntry;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
class ServiceClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ServiceRegistryClient $serviceRegistryClient,
        private readonly string $contenaVersion,
    ) {
    }

    public function newFor(ServiceEntry $entry): ServiceClient
    {
        return new ServiceClient(
            $this->client->withOptions([
                'base_uri' => $entry->host,
            ]),
            $this->contenaVersion,
            $entry,
        );
    }

    public function fromName(string $name): ServiceClient
    {
        return $this->newFor(
            $this->serviceRegistryClient->get($name)
        );
    }
}
