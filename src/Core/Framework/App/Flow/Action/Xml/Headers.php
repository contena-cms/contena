<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Headers extends XmlElement
{
    /**
     * @var list<Parameter>
     */
    protected array $parameters;

    /**
     * @return list<Parameter>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('parameter') as $parameters) {
            $values[] = Parameter::fromXml($parameters);
        }

        return ['parameters' => $values];
    }
}
