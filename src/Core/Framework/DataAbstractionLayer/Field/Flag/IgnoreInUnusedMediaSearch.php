<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

/**
 * Excludes a technical media association from being treated as real media usage by `media:delete-unused`.
 */
class IgnoreInUnusedMediaSearch extends Flag
{
    public function parse(): \Generator
    {
        yield 'ignore_in_unused_media_search' => true;
    }
}
