<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Event\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class CustomEvents extends XmlElement
{
    /**
     * @var list<CustomEvent>
     */
    protected array $customEvent;

    /**
     * @return list<CustomEvent>
     */
    public function getCustomEvents(): array
    {
        return $this->customEvent;
    }

    protected static function parse(\DOMElement $element): array
    {
        $events = [];
        foreach ($element->getElementsByTagName('flow-event') as $flowEvent) {
            $events[] = CustomEvent::fromXml($flowEvent);
        }

        return ['customEvent' => $events];
    }
}
