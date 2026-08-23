<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Parser;

/**
 * @codeCoverageIgnore Simple DTO with no business logic
 */
class ParseIssue
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $lineContent,
        public readonly string $reason,
        public readonly ParseIssueSeverity $severity
    ) {
    }
}
