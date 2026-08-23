<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Util\Json;

#[McpResource(
    uri: 'contena://entities',
    name: 'contena-entity-list',
    description: 'List of all registered Contena entity names'
)]
class EntityListResource
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $entities = [];
        foreach ($this->registry->getDefinitions() as $definition) {
            $entities[] = $definition->getEntityName();
        }

        sort($entities);

        return [
            'uri' => 'contena://entities',
            'mimeType' => 'application/json',
            'text' => Json::encode($entities),
        ];
    }
}
