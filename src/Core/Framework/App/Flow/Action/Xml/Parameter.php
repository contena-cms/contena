<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Parameter extends XmlElement
{
    protected string $type;

    protected string $name;

    protected string $value;

    protected string $id;

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            if (!$attribute instanceof \DOMAttr) {
                continue;
            }

            $values[self::kebabCaseToCamelCase($attribute->name)] = $attribute->value;
        }

        return $values;
    }
}
