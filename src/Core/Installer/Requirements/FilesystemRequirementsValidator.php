<?php declare(strict_types=1);

namespace Contena\Core\Installer\Requirements;

use Contena\Core\Installer\Requirements\Struct\PathCheck;
use Contena\Core\Installer\Requirements\Struct\RequirementCheck;
use Contena\Core\Installer\Requirements\Struct\RequirementsCheckCollection;

/**
 * @internal
 */
class FilesystemRequirementsValidator implements RequirementsValidatorInterface
{
    private const array NEEDED_DIRECTORY_PATHS = [
        '.',
        'var/cache/',
        'public/',
    ];

    public function __construct(private readonly string $projectDir)
    {
    }

    public function validateRequirements(RequirementsCheckCollection $checks): RequirementsCheckCollection
    {
        foreach (self::NEEDED_DIRECTORY_PATHS as $path) {
            $absolutePath = $this->projectDir . '/' . $path;

            $checks->add(new PathCheck(
                $path,
                $this->existsAndIsWritable($absolutePath) ? RequirementCheck::STATUS_SUCCESS : RequirementCheck::STATUS_ERROR
            ));
        }

        return $checks;
    }

    private function existsAndIsWritable(string $path): bool
    {
        return \is_dir($path) && \is_readable($path) && \is_writable($path);
    }
}
