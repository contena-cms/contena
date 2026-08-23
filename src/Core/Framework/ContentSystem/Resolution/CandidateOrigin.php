<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

/**
 * @internal
 */
enum CandidateOrigin: string
{
    case Parent = 'parent';
    case Loader = 'loader';
    case Stored = 'stored';
}
