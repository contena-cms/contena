<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Parser;

/**
 * @codeCoverageIgnore Simple enum with no logic - covered by ParseIssue and ParsedRobots tests
 */
enum ParseIssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}
