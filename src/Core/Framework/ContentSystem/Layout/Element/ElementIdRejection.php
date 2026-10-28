<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element;

use Contena\Core\Framework\Log\Package;

/**
 * Why {@see ElementIdRule} refused an id. Each enforcement site words it for its own audience, so the rule
 * itself carries no message text.
 *
 * @internal
 */
#[Package('framework')]
enum ElementIdRejection
{
    case ReservedLiteral;

    case IntegerLiteral;

    case LineTerminator;
}
