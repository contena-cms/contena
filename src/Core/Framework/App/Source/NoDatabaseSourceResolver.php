<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Source;

use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
class NoDatabaseSourceResolver
{
    public function __construct(private readonly ActiveAppsLoader $activeAppsLoader)
    {
    }

    public function filesystem(string $appName): Filesystem
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $activeApp) {
            if ($activeApp['name'] === $appName) {
                return new Filesystem($activeApp['path']);
            }
        }

        throw AppException::notFoundByField($appName, 'name');
    }
}
