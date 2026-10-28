<?php declare(strict_types=1);

namespace Contena\Core\Installer\Finish;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class SystemLocker
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function lock(): void
    {
        file_put_contents($this->projectDir . '/install.lock', date('YmdHi'));
    }
}
