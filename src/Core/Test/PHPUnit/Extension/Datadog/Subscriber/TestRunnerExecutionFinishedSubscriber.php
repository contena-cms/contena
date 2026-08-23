<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Contena\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Gateway\DatadogGateway;

/**
 * @internal
 */
class TestRunnerExecutionFinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function __construct(
        private readonly DatadogPayloadCollection $failedTests,
        private readonly DatadogPayloadCollection $slowTests,
        private readonly DatadogPayloadCollection $skippedTests,
        private readonly DatadogGateway $gateway
    ) {
    }

    public function notify(ExecutionFinished $event): void
    {
        $failedTests = array_values($this->failedTests->map(static fn (DatadogPayload $payload) => $payload->serialize()));
        $slowTests = array_values($this->slowTests->map(static fn (DatadogPayload $payload) => $payload->serialize()));
        $skippedTests = array_values($this->skippedTests->map(static fn (DatadogPayload $payload) => $payload->serialize()));

        $this->gateway->sendLogs(array_merge($failedTests, $slowTests, $skippedTests));
    }
}
