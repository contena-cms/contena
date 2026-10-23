<?php declare(strict_types=1);

namespace Contena\Core\Profiling\Integration;

use Contena\Core\Framework\Log\Package;

/**
 * @internal experimental atm
 */
interface ProfilerInterface
{
    public function start(string $title, string $category, array $tags): void;

    public function stop(string $title): void;
}
