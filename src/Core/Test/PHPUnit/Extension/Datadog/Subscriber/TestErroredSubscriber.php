<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\Telemetry\HRTime;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Contena\Core\Test\PHPUnit\Extension\Datadog\DatadogPayload;
use Contena\Core\Test\PHPUnit\Extension\Datadog\DatadogPayloadCollection;

/**
 * @internal
 */
class TestErroredSubscriber implements ErroredSubscriber
{
    public function __construct(
        private readonly TimeKeeper $timeKeeper,
        private readonly DatadogPayloadCollection $erroredTests
    ) {
    }

    public function notify(Errored $event): void
    {
        $time = $event->telemetryInfo()->time();

        $duration = $this->timeKeeper->stop(
            $event->test()->id(),
            HRTime::fromSecondsAndNanoseconds(
                $time->seconds(),
                $time->nanoseconds(),
            ),
        );

        $payload = new DatadogPayload(
            'phpunit',
            'phpunit,test:errored',
            $event->asString(),
            'PHPUnit',
            $event->test()->id(),
            $duration->asFloat()
        );

        $this->erroredTests->set($event->test()->id(), $payload);
    }
}
