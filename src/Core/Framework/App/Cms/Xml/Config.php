<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Cms\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;
use Contena\Core\Framework\App\Manifest\XmlParserUtils;

/**
 * @internal
 *
 * @phpstan-type ConfigArray array<string, array{source: string, value: string}>
 */
class Config extends XmlElement
{
    /**
     * @var ConfigArray
     */
    protected array $items = [];

    /**
     * @return ConfigArray
     */
    public function toArray(string $defaultLocale): array
    {
        return $this->items;
    }

    protected static function parse(\DOMElement $element): array
    {
        $config = [];

        foreach ($element->getElementsByTagName('config-value') as $configValue) {
            $config[XmlParserUtils::kebabCaseToCamelCase($configValue->getAttribute('name'))] = [
                'source' => $configValue->getAttribute('source'),
                'value' => $configValue->getAttribute('value'),
            ];
        }

        return ['items' => $config];
    }
}
