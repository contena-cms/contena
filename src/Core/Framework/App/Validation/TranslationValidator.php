<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class TranslationValidator extends AbstractManifestValidator
{
    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();
        $error = $manifest->getMetadata()->validateTranslations();

        if ($error !== null) {
            $errors->add($error);
        }

        return $errors;
    }
}
