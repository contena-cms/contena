<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Exception\AppValidationException;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class ManifestValidator
{
    /**
     * @param iterable<AbstractManifestValidator> $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    public function validate(Manifest $manifest, Context $context): void
    {
        $errors = new ErrorCollection();
        foreach ($this->validators as $validator) {
            $errors->addErrors($validator->validate($manifest, $context));
        }

        if ($errors->count() === 0) {
            return;
        }

        throw new AppValidationException($manifest->getMetadata()->getName(), $errors);
    }
}
