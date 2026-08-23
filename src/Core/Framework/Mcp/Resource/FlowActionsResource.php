<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Util\Json;

#[McpResource(
    uri: 'contena://flow-actions',
    name: 'contena-flow-actions',
    description: 'All registered Contena flow actions available in Flow Builder automations.'
)]
class FlowActionsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FlowActionCollector $collector,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $context = $this->contextProvider->getContext();
        $result = $this->collector->collect($context);

        $actions = [];
        foreach ($result as $action) {
            $actions[] = [
                'name' => $action->getName(),
                'requirements' => $action->getRequirements(),
                'delayable' => $action->getDelayable(),
            ];
        }

        usort($actions, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return [
            'uri' => 'contena://flow-actions',
            'mimeType' => 'application/json',
            'text' => Json::encode($actions),
        ];
    }
}
