<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class TranslationValidator extends AbstractManifestValidator
{
    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $error = $manifest->getMetadata()->validateTranslations();

        return $error === null ? [] : [$error];
    }
}
