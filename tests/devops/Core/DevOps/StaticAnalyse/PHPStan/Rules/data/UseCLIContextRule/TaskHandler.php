<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
final class TaskHandler extends ScheduledTaskHandler
{
    public function run(): void
    {
        Context::createDefaultContext();
    }
}
