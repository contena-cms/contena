<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use Contena\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;

/**
 * @internal
 */
class MarkExecutionStartedSubscriber implements ExecutionStartedSubscriber
{
    public function notify(ExecutionStarted $event): void
    {
        CompletionGuard::$executionStarted = true;
    }
}
