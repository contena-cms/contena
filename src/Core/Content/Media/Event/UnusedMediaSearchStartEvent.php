<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Event;


/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class UnusedMediaSearchStartEvent
{
    public function __construct(public int $totalMedia, public int $totalMediaDeletionCandidates)
    {
    }
}
