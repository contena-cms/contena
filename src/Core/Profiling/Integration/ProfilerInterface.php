<?php declare(strict_types=1);

namespace Contena\Core\Profiling\Integration;

/**
 * @internal experimental atm
 */
interface ProfilerInterface
{
    /**
     * @param array<string> $tags
     */
    public function start(string $title, string $category, array $tags): void;

    public function stop(string $title): void;
}
