<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Tool;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Contena\Core\Framework\Mcp\McpToolSchemaNormalizer;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Contena\Core\Framework\Util\Json;

/**
 * @internal
 */
abstract class AbstractToolSearchTool extends McpToolResponse
{
    final public const string NAME = 'contena-tool-search';

    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly ToolSearch $search,
        private readonly ?McpAllowlistProvider $allowlistProvider = null,
    ) {
    }

    public function __invoke(string $query, int $maxResults = 3): string
    {
        $allowlist = $this->allowlistProvider?->forCurrentRequest();
        $tools = [];

        foreach ($this->registry->getTools()->references as $tool) {
            \assert($tool instanceof Tool);

            if ($tool->name === self::NAME) {
                continue;
            }

            if ($allowlist?->tools !== null && !\in_array($tool->name, $allowlist->tools, true)) {
                continue;
            }

            $tools[] = $tool;
        }

        $results = [];
        foreach ($this->search->search($tools, $query, min($maxResults, 20)) as $result) {
            $toolData = json_decode(Json::encode($result->tool), true, 512, \JSON_THROW_ON_ERROR);
            \assert(\is_array($toolData));
            $toolData = McpToolSchemaNormalizer::normalizeTool($toolData);

            $results[] = [
                'tool' => $toolData,
                'score' => $result->score,
                'matchedIn' => $result->matchedIn,
            ];
        }

        $meta = [
            'query' => $query,
            'totalCandidates' => \count($tools),
        ];

        $usage = $this->usageHint();
        if ($usage !== null) {
            $meta['usage'] = $usage;
        }

        return $this->success($results, $meta);
    }

    /**
     * Optional guidance appended to the search result telling the model how to make a matched
     * tool callable when the client cannot invoke it directly from the inline result. Null when
     * the scope has no progressive disclosure and advertises all tools.
     */
    protected function usageHint(): ?string
    {
        return null;
    }
}
