<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action\Xml;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Actions extends XmlElement
{
    /**
     * @var list<Action>
     */
    protected array $actions;

    /**
     * @return list<Action>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    protected static function parse(\DOMElement $element): array
    {
        $actions = [];
        foreach ($element->getElementsByTagName('flow-action') as $flowAction) {
            $actions[] = Action::fromXml($flowAction);
        }

        return ['actions' => $actions];
    }
}
