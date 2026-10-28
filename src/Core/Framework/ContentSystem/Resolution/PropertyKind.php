<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
enum PropertyKind: string
{
    case Primitive = 'primitive';
    case Reference = 'reference';
}
