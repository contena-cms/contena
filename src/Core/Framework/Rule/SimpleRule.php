<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class SimpleRule extends Rule
{
    final public const RULE_NAME = 'simple';

    /**
     * @internal
     */
    public function __construct(protected bool $match = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        return $this->match;
    }

    public function getConstraints(): array
    {
        return [
            'match' => RuleConstraints::bool(true),
        ];
    }
}
