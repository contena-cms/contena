<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;

/**
 * @internal
 */
final readonly class ToolSearchResult
{
    /**
     * @param list<string> $matchedIn
     */
    public function __construct(
        public Tool $tool,
        public float $score,
        public array $matchedIn,
    ) {
    }
}
