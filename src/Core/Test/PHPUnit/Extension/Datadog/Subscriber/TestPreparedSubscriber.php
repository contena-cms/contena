<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber;

use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Common\TimeKeeper;

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
