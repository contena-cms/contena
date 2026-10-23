<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Application;

use Contena\Core\Content\Media\Core\Params\MediaLocationStruct;
use Contena\Core\Content\Media\Core\Params\ThumbnailLocationStruct;

/**
 * @internal Just for abstraction between domain and infrastructure. No public API!
 */
interface MediaLocationBuilder
{
    /**
     * @param array<string> $ids
     *
     * @return array<string, MediaLocationStruct> indexed by id
     */
    public function media(array $ids): array;

    /**
     * @param array<string> $ids
     *
     * @return array<string, ThumbnailLocationStruct> indexed by id
     */
    public function thumbnails(array $ids): array;
}
