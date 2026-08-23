<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\CustomFieldRule;
use Contena\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;

/**
 * @final
 */
class MemberCustomFieldRule extends Rule
{
    final public const RULE_NAME = 'memberCustomField';

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
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        $member = $scope->getMember();

        if ($member === null) {
            return false;
        }

        $customFields = $member->getCustomFields() ?? [];

        return CustomFieldRule::match($this->renderedField, $this->renderedFieldValue, $this->operator, $customFields, $scope->getChannelContext());
    }

    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }
}
