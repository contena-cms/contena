<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Script\Execution;

use Contena\Core\Framework\Script\Execution\Awareness\StoppableHook;

/**
 * @internal
 */
class StoppableTestHook extends TestHook implements StoppableHook
{
    use StoppableHookTrait;
}
