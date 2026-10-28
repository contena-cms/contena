<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\ErrorCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
abstract class AbstractManifestValidator
{
    abstract public function validate(Manifest $manifest, Context $context): ErrorCollection;
}
