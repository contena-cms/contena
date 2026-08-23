<?php declare(strict_types=1);

namespace CtTestPlugin;

use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @final
 *
 * @internal
 */
#[AsMessageHandler(handles: CtTestTask::class)]
class CtTestTaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
    }
}
