<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Consent;

use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\System\Consent\ConsentDefinitionProvider;

/**
 * @internal only for use by the app-system
 */
class AppConsentDefinitionProvider implements ConsentDefinitionProvider
{
    public function __construct(private readonly AppFeatureStorage $storage)
    {
    }

    public function getConsentDefinitions(): array
    {
        $definitions = [];

        foreach ($this->storage->forActiveApps(ConsentConfig::class) as $feature) {
            $definitions[] = new AppConsentDefinition($feature->appName, $feature->config);
        }

        return $definitions;
    }
}
