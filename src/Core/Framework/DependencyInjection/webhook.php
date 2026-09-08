<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Media\File\TrustedUrlResolver;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Contena\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Webhook\Api\WebhookHealthController;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Contena\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
use Contena\Core\Framework\Webhook\Health\HealthConfig;
use Contena\Core\Framework\Webhook\Health\HttpErrorClassifier;
use Contena\Core\Framework\Webhook\Health\WebhookHealthTick;
use Contena\Core\Framework\Webhook\Hookable\CoreHookableEventDescriber;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Contena\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Contena\Core\Framework\Webhook\Hookable\WriteResultMerger;
use Contena\Core\Framework\Webhook\Outbox\RetryDelayCalculator;
use Contena\Core\Framework\Webhook\Outbox\StreamLockService;
use Contena\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Contena\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask;
use Contena\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Contena\Core\Framework\Webhook\Service\WebhookCleanup;
use Contena\Core\Framework\Webhook\Service\WebhookClient;
use Contena\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Contena\Core\Framework\Webhook\Service\WebhookHealthService;
use Contena\Core\Framework\Webhook\Service\WebhookLoader;
use Contena\Core\Framework\Webhook\Service\WebhookManager;
use Contena\Core\Framework\Webhook\Service\WebhookSigningSecretResolver;
use Contena\Core\Framework\Webhook\Subscriber\AppLifecycleWebhookHealthSubscriber;
use Contena\Core\Framework\Webhook\Subscriber\WebhookHealthNotificationSubscriber;
use Contena\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Contena\Core\Framework\Webhook\Transport\WebhookTransportFactory;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Contena\Core\Framework\Webhook\Validation\WebhookUrlWriteValidator;
use Contena\Core\Framework\Webhook\WebhookCacheClearer;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\Framework\Webhook\WebhookDispatcher;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(WebhookDispatcher::class)
        ->decorate('event_dispatcher', null, 100)
        ->args([
            service(WebhookDispatcher::class . '.inner'),
            service(WebhookManager::class),
        ]);

    $services->set(WebhookLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('contena.webhook.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 20,
                'connect_timeout' => 10,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('after', [
                        'allow_redirects',
                        service('contena.webhook.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('contena.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(WebhookClient::class)
        ->args([
            service('contena.webhook.guzzle'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set('contena.webhook.trusted_url_resolver', TrustedUrlResolver::class)
        ->args([
            null,
            true,
            param('contena.app_system.allowed_private_ip_addresses'),
        ]);

    $services->set('contena.webhook.guzzle.security_middleware', AppSystemHttpMiddleware::class)
        ->args([
            service('contena.webhook.trusted_url_resolver'),
            param('contena.app_system.allow_unencrypted_traffic'),
            true,
            param('contena.app_system.allowed_private_ip_addresses'),
            param('contena.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookTargetValidator::class)
        ->args([
            param('contena.app_system.allow_unencrypted_traffic'),
            param('contena.app_system.allowed_private_ip_addresses'),
            service('contena.webhook.trusted_url_resolver'),
            param('contena.app_system.enable_url_validation'),
        ]);

    $services->set(WebhookUrlWriteValidator::class)
        ->args([
            service(WebhookTargetValidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookOutboxStore::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(RetryDelayCalculator::class)
        ->args([
            service(SymfonyClockInterface::class),
        ]);

    $services->set(StreamLockService::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(WebhookHealthService::class)
        ->args([
            service(Connection::class),
            service(WebhookOutboxStore::class),
            service(HealthConfig::class),
            service(SymfonyClockInterface::class),
            service('event_dispatcher'),
            service('logger'),
        ]);

    $services->set(MySQLWebhookReceiver::class)
        ->args([
            service(StreamLockService::class),
            service(WebhookOutboxStore::class),
            service(SymfonyClockInterface::class),
            service('logger'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WebhookTransportFactory::class)
        ->args([
            service(WebhookOutboxStore::class),
            service_closure(MySQLWebhookReceiver::class),
            service(WebhookHealthTick::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(WebhookHealthTick::class)
        ->args([
            service(AbstractKeyValueStorage::class),
            service(SymfonyClockInterface::class),
            service('logger'),
            service(WebhookHealthService::class),
        ]);

    $services->set(WebhookManager::class)
        ->lazy()
        ->args([
            service(WebhookLoader::class),
            service(HookableEventFactory::class),
            service(AppLocaleProvider::class),
            service(AppPayloadServiceHelper::class),
            env('APP_URL'),
            param('kernel.contena_version'),
            service(WebhookDeliveryService::class),
            service(WebhookHealthService::class),
        ]);

    $services->set(WebhookCacheClearer::class)
        ->args([
            service(WebhookManager::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(HookableEventFactory::class)
        ->lazy()
        ->args([
            service(BusinessEventEncoder::class),
            service(WriteResultMerger::class),
            service(HookableEventCollector::class),
        ]);

    $services->set(WriteResultMerger::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(BusinessEventEncoder::class)
        ->args([
            service(JsonEntityEncoder::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(WebhookDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(WebhookEventLogDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(HookableEventCollector::class)
        ->args([
            service(BusinessEventCollector::class),
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('contena.entity.hookable'),
            tagged_iterator('contena.hookable_event.describer'),
        ]);

    $services->set(CoreHookableEventDescriber::class)
        ->tag('contena.hookable_event.describer');

    $services->set(WebhookSigningSecretResolver::class)
        ->args([
            service(Connection::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(WebhookDeliveryService::class)
        ->args([
            service(WebhookClient::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookSigningSecretResolver::class),
            service(WebhookOutboxStore::class),
            service(RetryDelayCalculator::class),
            service(MessageBusInterface::class),
            service(WebhookHealthService::class),
            service('logger'),
            service(HttpErrorClassifier::class),
        ]);

    $services->set(WebhookEventMessageHandler::class)
        ->args([
            service(WebhookDeliveryService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AppLifecycleWebhookHealthSubscriber::class)
        ->args([
            service(WebhookHealthService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookHealthNotificationSubscriber::class)
        ->args([
            service(Connection::class),
            service(SymfonyClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookHealthController::class)
        ->public()
        ->args([
            service(Connection::class),
            service(RateLimiter::class),
            service(WebhookHealthService::class),
            service(SymfonyClockInterface::class),
            service(AbstractKeyValueStorage::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(WebhookCleanup::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
            service(StreamLockService::class),
            service(SymfonyClockInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(CleanupWebhookEventLogTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupWebhookEventLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(WebhookCleanup::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(HealthConfig::class)
        ->args([
            param('contena.webhook.health.cooldown_schedule_seconds'),
            param('contena.webhook.health.degraded_threshold_count'),
            param('contena.webhook.health.non_transient_threshold_count'),
            param('contena.webhook.health.max_suspended_days'),
        ]);

    $services->set(HttpErrorClassifier::class);
};
