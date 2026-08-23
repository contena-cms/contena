<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Doctrine\Messenger\DoctrineTransportFactory;
use Contena\Core\Framework\Adapter\Messenger\Middleware\QueuedTimeMiddleware;
use Contena\Core\Framework\MessageQueue\Api\ConsumeMessagesController;
use Contena\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware;
use Contena\Core\Framework\MessageQueue\SendEmailMessageJsonSerializer;
use Contena\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Contena\Core\Framework\MessageQueue\Stats\MySQLStatsRepository;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\MessageQueue\Subscriber\EarlyReturnMessagesListener;
use Contena\Core\Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener;
use Contena\Core\Framework\MessageQueue\Subscriber\MessageQueueStatsSubscriber;
use Contena\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Contena\Core\Framework\MessageQueue\Telemetry\MessageQueueTelemetrySubscriber;
use Contena\Core\Framework\MessageQueue\Telemetry\MessengerQueueDepthCollector;
use Contena\Core\Framework\MessageQueue\Telemetry\WorkerMessageTimingHelper;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(EarlyReturnMessagesListener::class);

    $services->set(MessageQueueSizeRestrictListener::class)
        ->args([
            service(MessageSizeCalculator::class),
            param('contena.messenger.enforce_message_size'),
            param('contena.messenger.message_max_kib_size'),
        ])
        ->tag('kernel.event_listener', ['event' => SendMessageToTransportsEvent::class]);

    $services->set(MessageQueueStatsSubscriber::class)
        ->args([
            service(StatsService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MessageGroupResolver::class);

    $services->set(WorkerMessageTimingHelper::class);

    $services->set(MessageQueueTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
            service(MessageSizeCalculator::class),
            service(MessageGroupResolver::class),
            service(WorkerMessageTimingHelper::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');

    $services->set(MessengerQueueDepthCollector::class)
        ->args([
            service('messenger.receiver_locator'),
            service('logger'),
        ])
        ->tag('contena.telemetry.periodic_metric_collector');

    // Controller
    $services->set(ConsumeMessagesController::class)
        ->public()
        ->args([
            service('messenger.receiver_locator'),
            service('messenger.default_bus'),
            service('messenger.listener.stop_worker_on_restart_signal_listener'),
            service(EarlyReturnMessagesListener::class),
            service(MessageQueueStatsSubscriber::class),
            param('messenger.default_transport_name'),
            param('contena.admin_worker.memory_limit'),
            param('contena.admin_worker.poll_interval'),
            service('lock.factory'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set('messenger.transport.doctrine.factory', DoctrineTransportFactory::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(SendEmailMessageJsonSerializer::class)
        ->tag('serializer.normalizer');

    $services->set(MessageSizeCalculator::class)
        ->args([
            service('messenger.default_serializer'),
        ]);

    $services->set(RoutingOverwriteMiddleware::class)
        ->args([
            param('contena.messenger.routing_overwrite'),
        ]);

    $services->set(MySQLStatsRepository::class)
        ->args([
            service(Connection::class),
            param('contena.messenger.stats.time_span'),
        ]);

    $services->set(StatsService::class)
        ->args([
            service(MySQLStatsRepository::class),
            param('contena.messenger.stats.enabled'),
            service(ClockInterface::class),
        ]);

    $services->set(QueuedTimeMiddleware::class);
};
