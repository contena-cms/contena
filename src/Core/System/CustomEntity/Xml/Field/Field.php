<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Xml\Field;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;
use Contena\Core\Framework\App\Manifest\XmlParserUtils;

/**
 * @internal
 */
abstract class Field extends XmlElement
{
    protected string $name;

    protected bool $channelApiAware;

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['extensions']);

        return $data;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isChannelApiAware(): bool
    {
        return $this->channelApiAware;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseAttributes($element);
        $values += XmlParserUtils::parseChildren($element);

        return $values;
    }
}
