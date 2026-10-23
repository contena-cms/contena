<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use Contena\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * @internal
 */
class MarkExecutionFinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        CompletionGuard::$executionFinished = true;
    }
}
