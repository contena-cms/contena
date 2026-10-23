<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Monolog\Handler\NullHandler;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Contena\Core\Content\Media\File\TrustedUrlResolver;
use Contena\Core\Content\Test\Category\Service\CountingEntityReader;
use Contena\Core\Content\Test\Category\Service\CountingEntitySearcher;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Contena\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\Test\Api\Acl\fixtures\AclTestController;
use Contena\Core\Framework\Test\App\StaticTrustedUrlResolverFactory;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateTimeDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\NamedOptionalGroupDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\RootDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubCascadeDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubManyDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedReferenceDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WriteProtectedRelationDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\Validation\TestDefinition\TestTranslationDefinition;
use Contena\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Contena\Core\Framework\Test\MessageQueue\fixtures\TestMessageHandler;
use Contena\Core\Framework\Test\TestCacheClearer;
use Contena\Core\Framework\Test\TestCaseHelper\StoreApiSessionListener;
use Contena\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Contena\Core\Framework\Test\TestSessionStorageFactory;
use Contena\Core\Framework\Test\Webhook\StaticWebhookTargetValidatorFactory;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\Test\Integration\App\GuzzleHistoryCollector;
use Contena\Core\Test\Integration\App\TestAppServer;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\TraceableMessageBus;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('monolog', [
        'handlers' => [
            'business_event_handler_discard' => [
                'id' => NullHandler::class,
                'type' => 'service',
                'priority' => 1,
                'channels' => ['business_events'],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

    $services->set(TestPaymentHandler::class)
        ->args([
            service(OrderTransactionStateHandler::class),
        ])
        ->tag('contena.payment.method');

    $services->set(MemoryAdapterFactory::class)
        ->tag('contena.filesystem.factory');

    $services->set(NamedDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(NamedOptionalGroupDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(RootDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'root']);

    $services->set(SubDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'root_sub']);

    $services->set(SubCascadeDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'root_sub_cascade']);

    $services->set(SubManyDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'root_sub_many']);

    $services->set(TestDefinition::class)
        ->tag('contena.entity.definition', ['entity' => '_test_lock']);

    $services->set(TestTranslationDefinition::class)
        ->tag('contena.entity.definition', ['entity' => '_test_lock_translation']);

    $services->set(CustomFieldTestDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'attribute_test']);

    $services->set(CustomFieldTestTranslationDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'attribute_test_translation']);

    $services->set(WriteProtectedDefinition::class)
        ->tag('contena.entity.definition', ['entity' => '_test_nullable']);

    $services->set(WriteProtectedRelationDefinition::class)
        ->tag('contena.entity.definition', ['entity' => '_test_relation']);

    $services->set(WriteProtectedReferenceDefinition::class)
        ->tag('contena.entity.definition', ['entity' => '_test_nullable_reference']);

    $services->set(ExtendedBlogDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'extended_product']);

    $services->set(DateTimeDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'date_time_test']);

    $services->alias('messenger.test_receiver_locator', 'messenger.receiver_locator')
        ->public();

    $services->set('messenger.bus.test_contena', TraceableMessageBus::class)
        ->decorate('messenger.default_bus')
        ->args([
            service('messenger.bus.test_contena.inner'),
        ]);

    $services->set('mailer.mailer', Mailer::class)
        ->args([
            service('mailer.transports'),
            service('messenger.default_bus'),
            service('debug.event_dispatcher')->ignoreOnInvalid(),
        ]);

    $services->alias('test.browser', 'test.client');

    $services->set(StoreApiSessionListener::class)
        ->tag('kernel.event_subscriber');

    // innermost decorator, above core (up to 1000) and plugin priorities, so nested re-dispatches still reach the hooks
    $services->set(EventHookDispatcher::class)
        ->decorate('event_dispatcher', null, 100000)
        ->args([
            service(EventHookDispatcher::class . '.inner'),
        ]);

    $services->set('test.client', TestBrowser::class)
        ->share(false)
        ->public()
        ->args([
            service('kernel'),
            param('test.client.parameters'),
            service('test.client.history'),
            service('test.client.cookiejar'),
        ]);

    $services->set(NullHandler::class);

    $services->set(TestMessageHandler::class)
        ->tag('messenger.message_handler');

    $services->set(CountingEntityReader::class)
        ->decorate(EntityReaderInterface::class)
        ->args([
            service(CountingEntityReader::class . '.inner'),
        ]);

    $services->set(CountingEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class)
        ->args([
            service(CountingEntitySearcher::class . '.inner'),
        ]);

    $services->set(TestCacheClearer::class)
        ->args([
            [
                service('cache.object'),
                service('cache.http'),
                service('cache.rate_limiter'),
            ],
            service('cache_clearer'),
            param('kernel.cache_dir'),
        ]);

    $services->set('contena.app_system.guzzle', Client::class)
        ->args([
            [
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service(TestAppServer::class),
                    ])
                    ->call('after', [
                        'allow_redirects',
                        service('contena.app_system.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('contena.app_system.guzzle.middleware'),
                    ])
                    ->call('push', [
                        service('test.guzzle.history.middleware'),
                    ]),
            ],
        ]);

    $services->set('contena.app_system.trusted_url_resolver', TrustedUrlResolver::class)
        ->factory([StaticTrustedUrlResolverFactory::class, 'create'])
        ->args([
            param('contena.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('contena.webhook.trusted_url_resolver', TrustedUrlResolver::class)
        ->factory([StaticTrustedUrlResolverFactory::class, 'create'])
        ->args([
            param('contena.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('contena.webhook.guzzle', Client::class)
        ->args([
            [
                'allow_redirects' => AuthMiddleware::ALLOW_REDIRECTS,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->args([
                        service(TestAppServer::class),
                    ])
                    ->call('after', [
                        'allow_redirects',
                        service('contena.webhook.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('contena.app_system.guzzle.middleware'),
                    ])
                    ->call('push', [
                        service('test.guzzle.history.middleware'),
                    ]),
            ],
        ]);

    $services->set('Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator', WebhookTargetValidator::class)
        ->factory([StaticWebhookTargetValidatorFactory::class, 'create'])
        ->args([
            param('contena.app_system.allow_unencrypted_traffic'),
            param('contena.app_system.allowed_private_ip_addresses'),
            param('contena.app_system.enable_url_validation'),
        ]);

    $services->set(TestAppServer::class)
        ->args([
            service(MockHandler::class),
        ]);

    $services->set(MockHandler::class)
        ->public()
        ->args([
            [],
        ]);

    $services->set('test.guzzle.history.middleware', 'callable')
        ->factory([service(GuzzleHistoryCollector::class), 'getHistoryMiddleWare']);

    $services->set(GuzzleHistoryCollector::class)
        ->public();

    $services->set(AclTestController::class)
        ->public();

    $services->set('test_payment_decoration', AppPaymentHandler::class)
        ->decorate(AppPaymentHandler::class)
        ->args([
            service(StateMachineRegistry::class),
            service(PaymentPayloadService::class),
            service('order_transaction_capture_refund.repository'),
            service('order_transaction.repository'),
            service('app.repository'),
            service(Connection::class),
        ]);

    $services->set(TestSessionStorageFactory::class)
        ->decorate('session.storage.factory.mock_file');
};
