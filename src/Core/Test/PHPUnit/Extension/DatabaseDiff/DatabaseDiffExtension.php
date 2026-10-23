<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\DatabaseDiff;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Contena\Core\Kernel;
use Contena\Core\Test\PHPUnit\Extension\DatabaseDiff\Subscriber\BeforeTestMethodCalledSubscriber;
use Contena\Core\Test\PHPUnit\Extension\DatabaseDiff\Subscriber\TestFinishedSubscriber;

/**
 * @internal
 */
class DatabaseDiffExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $dbState = new DbState(Kernel::getConnection());

        $facade->registerSubscribers(
            new BeforeTestMethodCalledSubscriber($dbState),
            new TestFinishedSubscriber($dbState)
        );
    }
}
