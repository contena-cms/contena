<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze;

use Contena\Core\Kernel;

/**
 * @internal
 */
class StaticAnalyzeKernel extends Kernel
{
    public function getCacheDir(): string
    {
        return \sprintf(
            '%s/var/cache/static_%s',
            $this->getProjectDir(),
            $this->getEnvironment(),
        );
    }
}
