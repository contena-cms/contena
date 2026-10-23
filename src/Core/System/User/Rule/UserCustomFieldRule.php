<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\CustomFieldRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class UserCustomFieldRule extends Rule
{
    final public const RULE_NAME = 'userCustomField';

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
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $user = $scope->getUser();

        if ($user === null) {
            return false;
        }

        $customFields = $user->getCustomFields() ?? [];

        return CustomFieldRule::match($this->renderedField, $this->renderedFieldValue, $this->operator, $customFields, $scope->getChannelContext());
    }

    public function getConstraints(): array
    {
        return CustomFieldRule::getConstraints($this->renderedField);
    }
}
