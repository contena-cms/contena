<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class InstanceService
{
    public function __construct(
        private readonly string $contenaVersion,
        private readonly ?string $instanceId
    ) {
    }

    public function getContenaVersion(): string
    {
        if (str_ends_with($this->contenaVersion, '-dev')) {
            return '___VERSION___';
        }

        return $this->contenaVersion;
    }

    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }
}
