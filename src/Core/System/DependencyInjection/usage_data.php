<?php declare(strict_types=1);

/**
 * @codeCoverageIgnore - DI wiring only
 */

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\App\ShopId\ShopIdProvider as FrameworkShopIdProvider;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\System\Consent\Service\ConsentService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\UsageData\Client\GatewayClient;
use Contena\Core\System\UsageData\Consent\ConsentReporter;
use Contena\Core\System\UsageData\EntitySync\CollectEntityDataMessageHandler;
use Contena\Core\System\UsageData\EntitySync\DispatchEntityMessageHandler;
use Contena\Core\System\UsageData\EntitySync\EntityDispatcher;
use Contena\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Contena\Core\System\UsageData\EntitySync\IterateEntityMessageHandler;
use Contena\Core\System\UsageData\ScheduledTask\CollectEntityDataTask;
use Contena\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Contena\Core\System\UsageData\Services\EntityDefinitionService;
use Contena\Core\System\UsageData\Services\EntityDispatchService;
use Contena\Core\System\UsageData\Services\GatewayStatusService;
use Contena\Core\System\UsageData\Services\ManyToManyAssociationService;
use Contena\Core\System\UsageData\Services\ShopIdProvider;
use Contena\Core\System\UsageData\Services\UsageDataAllowListService;
use Contena\Core\System\UsageData\Subscriber\ConsentStateChangedSubscriber;
use Contena\Core\System\UsageData\Subscriber\EntityDeleteSubscriber;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(EntityDeleteSubscriber::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(Connection::class),
            new Reference('clock'),
            new Reference(ConsentService::class),
            '%contena.usage_data.collection_enabled%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(IterateEntityMessageHandler::class)
        ->args([
            new Reference('messenger.default_bus'),
            new Reference(IterateEntitiesQueryBuilder::class),
            new Reference(ConsentService::class),
            new Reference(EntityDefinitionService::class),
            new Reference('logger'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DispatchEntityMessageHandler::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(ManyToManyAssociationService::class),
            new Reference(UsageDataAllowListService::class),
            new Reference(Connection::class),
            new Reference(EntityDispatcher::class),
            new Reference(ConsentService::class),
            new Reference(ShopIdProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CollectEntityDataMessageHandler::class)
        ->args([
            new Reference(EntityDispatchService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(EntityDispatcher::class)
        ->args([
            new Reference('contena.usage_data.gateway.client'),
            new Reference(InstanceService::class),
            new Reference(SystemConfigService::class),
            new Reference('clock'),
            '%kernel.environment%',
            '%contena.usage_data.gateway.dispatch_enabled%',
        ]);

    $services->set(IterateEntitiesQueryBuilder::class)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(Connection::class),
            '%contena.usage_data.gateway.batch_size%',
            new Reference('logger'),
        ]);

    $services->set(EntityDispatchService::class)
        ->lazy(true)
        ->args([
            new Reference(EntityDefinitionService::class),
            new Reference(AbstractKeyValueStorage::class),
            new Reference('messenger.default_bus'),
            new Reference(GatewayStatusService::class),
            new Reference(ShopIdProvider::class),
            new Reference(SystemConfigService::class),
            new Reference(ConsentService::class),
            '%contena.usage_data.collection_enabled%',
        ]);

    $services->set(ManyToManyAssociationService::class)
        ->args([
            new Reference(Connection::class),
        ]);

    $services->set(EntityDefinitionService::class)
        ->args([
            new TaggedIteratorArgument('contena.entity.definition'),
            new Reference(UsageDataAllowListService::class),
        ]);

    $services->set(ConsentReporter::class)
        ->args([
            new Reference('contena.usage_data.gateway.client'),
            new Reference(ShopIdProvider::class),
            new Reference(SystemConfigService::class),
            new Reference(InstanceService::class),
            '%env(APP_URL)%',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UsageDataAllowListService::class);

    $services->set(ShopIdProvider::class)
        ->args([
            new Reference(FrameworkShopIdProvider::class),
            new Reference(SystemConfigService::class),
        ]);

    $services->set(GatewayStatusService::class)
        ->args([
            new Reference(GatewayClient::class),
        ]);

    $services->set(GatewayClient::class)
        ->args([
            new Reference('contena.usage_data.gateway.client'),
            new Reference(ShopIdProvider::class),
        ]);

    $services->set(CollectEntityDataTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CollectEntityDataTaskHandler::class)
        ->args([
            new Reference('scheduled_task.repository'),
            new Reference('logger'),
            new Reference(EntityDispatchService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set('contena.usage_data.gateway.client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([
            [
                'base_uri' => '%contena.usage_data.gateway.base_uri%',
            ],
        ]);

    $services->set(ConsentStateChangedSubscriber::class)
        ->args([
            new Reference(EntityDispatchService::class),
            new Reference(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');
};
