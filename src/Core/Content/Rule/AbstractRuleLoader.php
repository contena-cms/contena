<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\Context;

abstract class AbstractRuleLoader
{
    abstract public function getDecorated(): AbstractRuleLoader;

    abstract public function load(Context $context): RuleCollection;
}
