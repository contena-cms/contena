<?php declare(strict_types=1);

namespace Contena\Core\System\Consent;

/**
 * Source of consent definitions registered at runtime, for example by installed apps.
 *
 * @internal
 */
interface ConsentDefinitionProvider
{
    /**
     * @return list<ConsentDefinition>
     */
    public function getConsentDefinitions(): array;
}
