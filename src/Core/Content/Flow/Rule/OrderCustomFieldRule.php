<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Contena\Core\Framework\Rule\CustomFieldRule;
use Contena\Core\Framework\Rule\FlowRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class OrderCustomFieldRule extends FlowRule
{
    final public const RULE_NAME = 'orderCustomField';

    /**
     * @var array<string|int|bool|float>|string|int|bool|float|null
     */
    protected array|string|int|bool|float|null $renderedFieldValue = null;

    protected ?string $selectedField = null;

    protected ?string $selectedFieldSet = null;

    /**
     * @param array<string, string|array<string, string>> $renderedField
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected array $renderedField = []
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof FlowRuleScope) {
            return false;
        }

        $orderCustomFields = $scope->getOrder()->getCustomFields() ?? [];

        return CustomFieldRule::match($this->renderedField, $this->renderedFieldValue, $this->operator, $orderCustomFields, $scope->getChannelContext());
    }

    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }
}
