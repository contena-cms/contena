<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Execution;

/**
 * @internal only rely on the concrete implementations
 */
abstract class FunctionHook extends Hook
{
    /**
     * Returns the name of the function.
     */
    abstract public function getFunctionName(): string;
}
