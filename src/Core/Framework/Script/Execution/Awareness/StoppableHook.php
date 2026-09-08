<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Execution\Awareness;

/**
 * @internal
 */
interface StoppableHook
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}
