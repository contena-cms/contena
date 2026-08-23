<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml;

use Contena\Core\Framework\Util\XmlElement;

/**
 * @internal
 */
class CustomFields extends XmlElement
{
    /**
     * @var list<CustomFieldSet>
     */
    protected array $customFieldSets = [];

    /**
     * @return list<CustomFieldSet>
     */
    public function getCustomFieldSets(): array
    {
        return $this->customFieldSets;
    }

    protected static function parse(\DOMElement $element): array
    {
        $customFieldSets = [];
        foreach ($element->getElementsByTagName('custom-field-set') as $customFieldSet) {
            $customFieldSets[] = CustomFieldSet::fromXml($customFieldSet);
        }

        return ['customFieldSets' => $customFieldSets];
    }
}
