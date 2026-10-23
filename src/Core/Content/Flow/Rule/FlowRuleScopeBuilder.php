<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class FlowRuleScopeBuilder implements ResetInterface
{
    /**
     * @var array<string, FlowRuleScope>
     */
    private array $scopes = [];


    public function reset(): void
    {
        $this->scopes = [];
    }

}
