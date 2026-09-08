<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Source;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
interface Source
{
    public static function name(): string;

    public function supports(AppEntity|Manifest $app): bool;

    public function filesystem(AppEntity|Manifest $app): Filesystem;

    /**
     * @param array<Filesystem> $filesystems
     */
    public function reset(array $filesystems): void;
}
