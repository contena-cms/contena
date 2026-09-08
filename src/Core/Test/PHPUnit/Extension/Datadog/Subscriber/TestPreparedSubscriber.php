<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use Contena\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;

/**
 * @internal
 */
class TestPreparedSubscriber implements PreparedSubscriber
{
    public function __construct(private readonly TimeKeeper $timeKeeper)
    {
    }

    public function notify(Prepared $event): void
    {
        $this->timeKeeper->start(
            $event->test()->id(),
            $event->telemetryInfo()->time()
        );
    }
}
