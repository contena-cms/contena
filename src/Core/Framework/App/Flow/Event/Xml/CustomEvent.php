<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Event\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 *
 * @phpstan-type CustomEventArrayType array{name: string|null, aware?: array<int, string|null>, requirements?: array<int, string|null>}
 */
class CustomEvent extends XmlElement
{
    public const REQUIRED_FIELDS = [
        'name',
    ];

    protected string $name;

    /**
     * @var list<string>
     */
    protected array $aware = [];

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getAware(): array
    {
        return $this->aware;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->nodeName === 'aware') {
                $values['aware'][] = $child->nodeValue;

                continue;
            }

            if ($child->nodeName === 'name') {
                $values['name'] = $child->nodeValue;
            }
        }

        return $values;
    }
}
