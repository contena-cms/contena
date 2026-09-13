<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element;

/**
 * Why {@see ElementIdRule} refused an id. Each enforcement site words it for its own audience, so the rule
 * itself carries no message text.
 *
 * @internal
 */
enum ElementIdRejection
{
    case ReservedLiteral;

    case IntegerLiteral;

    case LineTerminator;
}
