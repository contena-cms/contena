<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Manifest\Xml\RuleCondition;

use Contena\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal only for use by the app-system
 */
class RuleConditions extends XmlElement
{
    /**
     * @var list<RuleCondition>
     */
    protected array $ruleConditions = [];

    /**
     * @return list<RuleCondition>
     */
    public function getRuleConditions(): array
    {
        return $this->ruleConditions;
    }

    protected static function parse(\DOMElement $element): array
    {
        $ruleConditions = [];
        foreach ($element->getElementsByTagName('rule-condition') as $ruleCondition) {
            $ruleConditions[] = RuleCondition::fromXml($ruleCondition);
        }

        return ['ruleConditions' => $ruleConditions];
    }
}
