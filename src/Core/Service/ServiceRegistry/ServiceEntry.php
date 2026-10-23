<?php declare(strict_types=1);

namespace Contena\Core\Service\ServiceRegistry;


/**
 * @internal
 */
readonly class ServiceEntry
{
    public function __construct(
        public string $name,
        public string $description,
        public string $host,
        public string $appEndpoint,
        public bool $activateOnInstall = true,
    ) {
    }
}
