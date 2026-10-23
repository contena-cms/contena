<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\AppNameError;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class AppNameValidator extends AbstractManifestValidator
{
    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        $errors = new ErrorCollection();

        $appName = strtolower(substr($manifest->getPath(), (int) strrpos($manifest->getPath(), '/') + 1));

        if ($appName !== strtolower($manifest->getMetadata()->getName())) {
            $errors->add(new AppNameError($manifest->getMetadata()->getName()));
        }

        return $errors;
    }
}
