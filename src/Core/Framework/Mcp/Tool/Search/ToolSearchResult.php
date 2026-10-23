<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use Contena\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
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
