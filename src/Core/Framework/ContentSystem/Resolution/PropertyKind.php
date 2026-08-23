<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Resolution;

/**
 * @internal
 */
enum PropertyKind: string
{
    case Primitive = 'primitive';
    case Reference = 'reference';
}
