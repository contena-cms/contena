<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Source;

use Symfony\Component\Filesystem\Path;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
class TemporaryDirectoryFactory
{
    public function path(): string
    {
        return Path::join(sys_get_temp_dir(), 'apps');
    }
}
