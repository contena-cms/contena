<?php declare(strict_types=1);

namespace Contena\Core\Service\ServiceRegistry;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
readonly class ServiceEntry
{
    public function __construct(
        public string $name,
        public string $description,
        public string $host,
        public string $appEndpoint,
        public bool $activateOnInstall,
        public ?ServiceStatus $status,
    ) {
    }
}
