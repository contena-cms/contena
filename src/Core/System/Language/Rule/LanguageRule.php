<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageException;

/**
 * @final
 */
class LanguageRule extends Rule
{
    final public const string RULE_NAME = 'language';

    /**
     * @internal
     *
     * @param list<string>|null $languageIds
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $languageIds = null,
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if ($this->languageIds === null) {
            throw LanguageException::unsupportedValue(\gettype($this->languageIds), self::class);
        }

        return RuleComparison::uuids([$scope->getContext()->getLanguageId()], $this->languageIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::uuidOperators(false),
            'languageIds' => RuleConstraints::uuids(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('languageIds', LanguageDefinition::ENTITY_NAME, true);
    }
}
