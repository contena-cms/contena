<?php declare(strict_types=1);

namespace Contena\Core\Test\PHPUnit\Extension\Datadog;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Test\PHPUnit\Extension\Common\TimeKeeper;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Gateway\DatadogGateway;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestErroredSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFailedSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestFinishedSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestPreparedSubscriber;
use Contena\Core\Test\PHPUnit\Extension\Datadog\Subscriber\TestRunnerExecutionFinishedSubscriber;

/**
 * @internal
 */
class DatadogExtension implements Extension
{
    public const int THRESHOLD_IN_SECONDS = 2;

    public const string GATEWAY_URL = 'https://http-intake.logs.datadoghq.eu/v1/input';

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $timeKeeper = new TimeKeeper();
        $failedTests = new DatadogPayloadCollection();
        $slowTests = new DatadogPayloadCollection();
        $erroredTests = new DatadogPayloadCollection();

        $facade->registerSubscribers(
            new TestPreparedSubscriber($timeKeeper),
            new TestFailedSubscriber($timeKeeper, $failedTests),
            new TestFinishedSubscriber($timeKeeper, $slowTests),
            new TestErroredSubscriber($timeKeeper, $erroredTests),
            new TestRunnerExecutionFinishedSubscriber(
                $failedTests,
                $slowTests,
                $erroredTests,
                new DatadogGateway(self::GATEWAY_URL)
            ),
        );
    }

    private function isEnabled(): bool
    {
        return EnvironmentHelper::hasVariable('DATADOG_API_KEY')
            && (EnvironmentHelper::getVariable('CI_COMMIT_REF_NAME') === 'trunk'
                || EnvironmentHelper::getVariable('CI_MERGE_REQUEST_EVENT_TYPE') === 'merge_train');
    }
}
