<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Mcp\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal only for use by the app-system
 */
class McpResources extends XmlElement
{
    /**
     * @var list<McpResource>
     */
    protected array $resources;

    /**
     * @return list<McpResource>
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        $resources = [];
        foreach ($element->getElementsByTagName('mcp-resource') as $resource) {
            $resources[] = McpResource::fromXml($resource);
        }

        return ['resources' => $resources];
    }
}
