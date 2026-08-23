<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Application;

/**
 * @internal Just for abstraction between domain and infrastructure. No public API!
 */
interface MediaPathStorage
{
    /**
     * @param array<string, string> $paths
     */
    public function media(array $paths): void;

    /**
     * @param array<string, string> $paths
     */
    public function thumbnails(array $paths): void;
}
