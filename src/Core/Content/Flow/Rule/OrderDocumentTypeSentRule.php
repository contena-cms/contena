<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\FlowRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class OrderDocumentTypeSentRule extends FlowRule
{
    public const RULE_NAME = 'orderDocumentTypeSent';

    /**
     * @param list<string> $documentIds
     *
     * @internal
     */
    public function __construct(
        public string $operator = Rule::OPERATOR_EQ,
        public ?array $documentIds = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['documentIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        if (!$documents = $scope->getOrder()->getDocuments()) {
            return false;
        }

        $sentTypeIds = [];
        foreach ($documents->getElements() as $document) {
            if ($document->getSent()) {
                $sentTypeIds[] = Feature::silent('v6.9.0.0', static fn (): string => $document->getDocumentTypeId());
            }
        }

        return RuleComparison::uuids(array_values(array_unique($sentTypeIds)), $this->documentIds, $this->operator);
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('documentIds', DocumentTypeDefinition::ENTITY_NAME, true);
    }
}
