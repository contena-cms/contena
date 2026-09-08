<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Validation\Error\AppNameError;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class AppNameValidator extends AbstractManifestValidator
{
    public function __construct(private readonly SourceResolver $sourceResolver)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $directory = strtolower(basename($this->sourceResolver->filesystemForManifest($manifest)->location));

        if ($directory === strtolower($manifest->getMetadata()->getName())) {
            return [];
        }

        return [new AppNameError($manifest->getMetadata()->getName())];
    }
}
