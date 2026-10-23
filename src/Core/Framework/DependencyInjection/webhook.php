<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Media\File\TrustedUrlResolver;
use Contena\Core\Framework\Api\Acl\AclRoleLoader;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Event\BusinessEventRegistry;
use Contena\Core\Framework\Webhook\Authorization\Ownership\AppIntegrationChangeSubscriber;
use Contena\Core\Framework\Webhook\Authorization\Ownership\RecordOwnerSubscriber;
use Contena\Core\Framework\Webhook\Authorization\Policy\AppEventPolicy;
use Contena\Core\Framework\Webhook\Authorization\Policy\NotHookablePolicy;
use Contena\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Contena\Core\Framework\Webhook\Authorization\Policy\PrivilegePolicy;
use Contena\Core\Framework\Webhook\Authorization\RestrictWriteSubscriber;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Contena\Core\Framework\Webhook\Authorization\WriteAuthorizer;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Framework\Webhook\Command\WebhookDrainToAsyncCommand;
use Contena\Core\Framework\Webhook\EventLog\WebhookEventLogDefinition;
use Contena\Core\Framework\Webhook\Handler\WebhookEventMessageHandler;
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
use Contena\Core\Framework\Webhook\Subscriber\RetryWebhookMessageFailedSubscriber;
use Contena\Core\Framework\Webhook\Transport\MySQLWebhookReceiver;
use Contena\Core\Framework\Webhook\Transport\WebhookTransportFactory;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Contena\Core\Framework\Webhook\Validation\WebhookUrlWriteValidator;
use Contena\Core\Framework\Webhook\WebhookCacheClearer;
use Contena\Core\Framework\Webhook\WebhookDefinition;
use Contena\Core\Framework\Webhook\WebhookDispatcher;
use Contena\Core\System\SystemConfig\SystemConfigService;
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
            service(AclRoleLoader::class),
        ]);

    $services->set('contena.webhook.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 20,
                'connect_timeout' => 10,
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
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
            service_closure('messenger.transport.async'),
            service_closure(MySQLWebhookReceiver::class),
        ])
        ->tag('messenger.transport_factory');

    $services->set(WebhookDrainToAsyncCommand::class)
        ->args([
            service(Connection::class),
            service(MessageBusInterface::class),
            service('logger'),
        ])
        ->tag('console.command')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(WebhookManager::class)
        ->lazy()
        ->args([
            service(WebhookLoader::class),
            service(HookableEventFactory::class),
            service(AppLocaleProvider::class),
            service(AppPayloadServiceHelper::class),
            service(WebhookClient::class),
            service(MessageBusInterface::class),
            env('APP_URL'),
            param('kernel.contena_version'),
            param('contena.admin_worker.enable_admin_worker'),
            service(WebhookDeliveryService::class),
            service(WebhookOutboxStore::class),
            service(PolicyRegistry::class),
        ]);

    $services->set(WebhookCacheClearer::class)
        ->args([
            service(WebhookManager::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(PolicyRegistry::class)
        ->args([
            tagged_iterator('contena.webhook.policy'),
            service('logger'),
        ]);

    $services->set(NotHookablePolicy::class)
        ->args([service(BusinessEventRegistry::class)])
        ->tag('contena.webhook.policy');

    $services->set(AppEventPolicy::class)
        ->tag('contena.webhook.policy');

    $services->set(PrivilegePolicy::class)
        ->args([service(AclRoleLoader::class)])
        ->tag('contena.webhook.policy')
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

    $services->set(RecordOwnerSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(AppIntegrationChangeSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WriteAuthorizer::class)
        ->args([
            service(SubscriptionValidator::class),
            service(WebhookLoader::class),
            service(AclRoleLoader::class),
        ]);

    $services->set(SubscriptionValidator::class)
        ->args([
            service(HookableEventCollector::class),
            service(PolicyRegistry::class),
        ]);

    $services->set(RestrictWriteSubscriber::class)
        ->args([service(WriteAuthorizer::class)])
        ->tag('kernel.event_subscriber');

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
            param('contena.admin_worker.enable_admin_worker'),
            param('contena.webhook.failure_strategy'),
        ]);

    $services->set(WebhookEventMessageHandler::class)
        ->args([
            service(WebhookClient::class),
            service(WebhookHealthService::class),
            service(WebhookOutboxStore::class),
            service(WebhookDeliveryService::class),
            service('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(RetryWebhookMessageFailedSubscriber::class)
        ->args([
            service(Connection::class),
            service(WebhookOutboxStore::class),
            param('contena.webhook.failure_strategy'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WebhookCleanup::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
            service(StreamLockService::class),
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
};
