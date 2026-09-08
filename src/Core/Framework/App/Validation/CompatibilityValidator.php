<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Validation;

use Composer\Semver\VersionParser;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Validation\Error\Error;
use Contena\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class CompatibilityValidator extends AbstractManifestValidator
{
    public function __construct(private readonly string $contenaVersion)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(Manifest $manifest, ?Context $context): array
    {
        $versionParser = new VersionParser();
        if ($manifest->getMetadata()->getCompatibility()->matches($versionParser->parseConstraints($this->contenaVersion))) {
            return [];
        }

        return [new IncompatibleAppError($manifest->getMetadata()->getName())];
    }
}
