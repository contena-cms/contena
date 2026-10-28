<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\CompletionGuard\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * @internal
 */
#[Package('framework')]
class MarkExecutionStartedSubscriber implements ExecutionStartedSubscriber
{
    public function notify(ExecutionStarted $event): void
    {
        CompletionGuard::$executionStarted = true;
    }
}
