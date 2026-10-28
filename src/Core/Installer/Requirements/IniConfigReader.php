<?php declare(strict_types=1);

namespace Contena\Core\Installer\Requirements;

use Contena\Core\Framework\Log\Package;

/**
 * Extracted to be able to mock all ini values
 *
 * @internal
 */
#[Package('framework')]
class IniConfigReader
{
    public function get(string $key): string
    {
        return (string) \ini_get($key);
    }
}
