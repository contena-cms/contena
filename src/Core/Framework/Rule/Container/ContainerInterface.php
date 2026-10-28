<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Container;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;

#[Package('fundamentals@after-sales')]
interface ContainerInterface
{
    /**
     * @param Rule[] $rules
     */
    public function setRules(array $rules): void;

    public function addRule(Rule $rule): void;
}
