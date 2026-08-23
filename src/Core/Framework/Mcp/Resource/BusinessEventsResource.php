<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Util\Json;

#[McpResource(
    uri: 'contena://business-events',
    name: 'contena-business-events',
    description: 'All registered Contena business events that can trigger flows and event actions.'
)]
class BusinessEventsResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventCollector $collector,
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

        $events = [];
        foreach ($result as $event) {
            $events[] = [
                'name' => $event->getName(),
                'class' => $event->getClass(),
                'data' => $event->getData(),
            ];
        }

        return [
            'uri' => 'contena://business-events',
            'mimeType' => 'application/json',
            'text' => Json::encode($events),
        ];
    }
}
