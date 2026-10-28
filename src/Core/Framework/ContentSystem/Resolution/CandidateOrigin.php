<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum CandidateOrigin: string
{
    case Parent = 'parent';
    case Loader = 'loader';
    case Stored = 'stored';
    case Root = 'root';
}
