<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
abstract class AbstractManifestValidator
{
    /**
     * @return list<Error>
     */
    abstract public function validate(Manifest $manifest, Context $context): array;
}
