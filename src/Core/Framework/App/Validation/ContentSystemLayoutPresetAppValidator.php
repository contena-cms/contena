<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ContentSystemLayoutPresetSchemaError;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class ContentSystemLayoutPresetAppValidator extends AbstractManifestValidator
{
    public function __construct(
        private readonly YamlLayoutPresetLoader $loader,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, Context $context): array
    {
        $presetsDir = $manifest->getPath() . '/Resources/content-system/presets';
        $appName = $manifest->getMetadata()->getName();

        try {
            $this->loader->loadDtosFromDirectory($presetsDir, 'app:' . $appName, $appName);
        } catch (ContentSystemException $e) {
            return [new ContentSystemLayoutPresetSchemaError(
                $presetsDir,
                $e->getMessage()
            )];
        }

        return [];
    }
}
