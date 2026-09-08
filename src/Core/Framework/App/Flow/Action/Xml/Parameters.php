<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Parameters extends XmlElement
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

        foreach ($element->getElementsByTagName('parameter') as $parameter) {
            $values[] = Parameter::fromXml($parameter);
        }

        return ['parameters' => $values];
    }
}
