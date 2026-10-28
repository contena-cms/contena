<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\App\Source\TemporaryDirectoryFactory as CoreTemporaryDirectoryFactory;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
class TemporaryDirectoryFactory extends CoreTemporaryDirectoryFactory
{
    public function __construct(private readonly string $projectDirectory)
    {
    }

    public function path(): string
    {
        return Path::join($this->projectDirectory, 'var/services');
    }
}
