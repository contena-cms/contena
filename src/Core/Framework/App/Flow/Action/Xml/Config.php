<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Config extends XmlElement
{
    /**
     * @var list<InputField>
     */
    protected array $config;

    /**
     * @return list<InputField>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('input-field') as $parameter) {
            $values[] = InputField::fromXml($parameter);
        }

        return ['config' => $values];
    }
}
