<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Result;

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

    /**
     * @return Result<list<Error>>
     */
    public function validate(Manifest $manifest, Context $context): Result
    {
        $errors = [];
        foreach ($this->validators as $validator) {
            $errors = [...$errors, ...$validator->validate($manifest, $context)];
        }

        return $errors === [] ? Result::ok() : Result::failed($errors);
    }
}
