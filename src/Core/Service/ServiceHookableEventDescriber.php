<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Contena\Core\Framework\Webhook\Hookable\HookableEventDescription;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ServiceHookableEventDescriber implements HookableEventDescriber
{
    public function describe(): array
    {
        return $this->getServiceEventDescriptions();
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return $this->getServiceEventDescriptions();
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function getServiceEventDescriptions(): array
    {
        return [
        ];
    }
}
