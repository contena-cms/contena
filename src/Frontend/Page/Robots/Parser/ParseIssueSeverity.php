<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Robots\Parser;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple enum with no logic - covered by ParseIssue and ParsedRobots tests
 */
#[Package('discovery')]
enum ParseIssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}
