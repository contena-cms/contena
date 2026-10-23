<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Flow\Controller\TriggerFlowController;
use Contena\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserAffiliateAndCampaignCodeAction;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\AddOrderAffiliateAndCampaignCodeAction;
use Contena\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserGroupAction;
use Contena\Core\Content\Flow\Dispatching\Action\ChangeUserStatusAction;
use Contena\Core\Content\Flow\Dispatching\Action\GrantDownloadAccessAction;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserGroupCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetOrderCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Contena\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutionTriggersListener;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Contena\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Contena\Core\Content\Flow\Dispatching\FlowDispatcher;
use Contena\Core\Content\Flow\Dispatching\FlowExecutor;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Content\Flow\Dispatching\FlowLoader;
use Contena\Core\Content\Flow\Dispatching\Storer\A11yRenderedDocumentStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\CustomAppStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\UserGroupStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\UserRecoveryStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\LanguageStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MessageStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\NewsletterRecipientStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\OrderStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\OrderTransactionStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\BlogStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\ChannelContextStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\AdminUserStorer;
use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Content\Flow\Indexing\FlowBuilder;
use Contena\Core\Content\Flow\Indexing\FlowIndexer;
use Contena\Core\Content\Flow\Indexing\FlowIndexerSubscriber;
use Contena\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserGroupProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\AdminUserRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DocumentResolver;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Telemetry\Telemetry;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_locator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FlowDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FlowSequenceDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FlowDispatcher::class)
        ->decorate('event_dispatcher', null, 1000)
        ->args([
            service(FlowDispatcher::class . '.inner'),
            service_locator([
                'logger' => service('logger'),
                Connection::class => service(Connection::class),
                FlowFactory::class => service(FlowFactory::class),
                FlowExecutor::class => service(FlowExecutor::class),
                FlowLoader::class => service(FlowLoader::class),
                BufferedFlowQueue::class => service(BufferedFlowQueue::class),
            ]),
        ]);

    $services->set(BufferedFlowQueue::class);

    $services->set(BufferedFlowExecutor::class)
        ->public()
        ->args([
            service(BufferedFlowQueue::class),
            service(FlowLoader::class),
            service(FlowFactory::class),
            service(FlowExecutor::class),
            service('logger'),
        ]);

    $services->set(BufferedFlowExecutionTriggersListener::class)
        ->args([
            service_locator([
                BufferedFlowExecutor::class => service(BufferedFlowExecutor::class),
            ]),
            service(BufferedFlowQueue::class),
        ])
        ->tag('kernel.event_subscriber');


    $services->set(FlowExecutor::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(AppFlowActionProvider::class),
            service(Connection::class),
            service(ExtensionDispatcher::class),
            service('logger'),
            tagged_iterator('flow.action', 'key'),
            service(FlowMetricsInstrumentor::class),
        ]);

    $services->set(TriggerGroupResolver::class);

    $services->set(FlowMetricsInstrumentor::class)
        ->args([
            service(Telemetry::class),
            service(TriggerGroupResolver::class),
        ]);

        $services->set(AddUserTagAction::class)
        ->args([
            service('user.repository'),
        ])
        ->tag('flow.action', ['priority' => 900, 'key' => 'action.add.user.tag']);

        $services->set(RemoveUserTagAction::class)
        ->args([
            service('user_tag.repository'),
        ])
        ->tag('flow.action', ['priority' => 700, 'key' => 'action.remove.user.tag']);

    $services->set(ChangeUserGroupAction::class)
        ->args([
            service('user.repository'),
        ])
        ->tag('flow.action', ['priority' => 690, 'key' => 'action.change.user.group']);

    $services->set(ChangeUserStatusAction::class)
        ->args([
            service('user.repository'),
        ])
        ->tag('flow.action', ['priority' => 680, 'key' => 'action.change.user.status']);

        $services->set(SendMailAction::class)
        ->args([
            service(MailService::class),
            service('mail_template.repository'),
            service('logger'),
            service('event_dispatcher'),
            service('mail_template_type.repository'),
            service(Connection::class),
            service(JsonEntityEncoder::class),
            service(DefinitionInstanceRegistry::class),
            param('contena.mail.update_mail_variables_on_send'),
        ])
        ->tag('flow.action', ['priority' => 500, 'key' => 'action.mail.send']);


        $services->set(SetUserCustomFieldAction::class)
        ->args([
            service(Connection::class),
            service('user.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.set.user.custom.field']);

        $services->set(SetUserGroupCustomFieldAction::class)
        ->args([
            service(Connection::class),
            service('user_group.repository'),
        ])
        ->tag('flow.action', ['priority' => 350, 'key' => 'action.set.user.group.custom.field']);

            $services->set(StopFlowAction::class)
        ->tag('flow.action', ['priority' => 1, 'key' => 'action.stop.flow']);

    $services->set(FlowActionCollector::class)
        ->args([
            tagged_iterator('flow.action'),
            service('event_dispatcher'),
            service('app_flow_action.repository'),
        ]);

    $services->set(FlowLoader::class)
        ->public()
        ->args([
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(CachedFlowLoader::class)
        ->decorate(FlowLoader::class, null, -1000)
        ->public()
        ->args([
            service(CachedFlowLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(FlowPayloadUpdater::class)
        ->args([
            service(Connection::class),
            service(FlowBuilder::class),
            service(CacheInvalidator::class),
        ]);

    $services->set(FlowIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('flow.repository'),
            service(FlowPayloadUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer');

    $services->set(FlowIndexerSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(FlowBuilder::class);

    $services->set(ChannelContextStorer::class)
        ->args([
            service(ChannelContextFactory::class),
        ])
        ->tag('flow.storer');

        $services->set(BlogStorer::class)
        ->args([
            service('blog.repository'),
            service('event_dispatcher'),
            service(BlogProvider::class),
        ])
        ->tag('flow.storer');

        $services->set(UserStorer::class)
        ->args([
            service('user.repository'),
            service('event_dispatcher'),
            service(UserProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(MailStorer::class)
        ->tag('flow.storer');

    $services->set(AdminUserStorer::class)
        ->args([
            service('admin_user_recovery.repository'),
            service('event_dispatcher'),
            service(AdminUserRecoveryProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(UserGroupStorer::class)
        ->args([
            service('user_group.repository'),
            service('event_dispatcher'),
            service(UserGroupProvider::class),
        ])
        ->tag('flow.storer');

    $services->set(UserRecoveryStorer::class)
        ->args([
            service('user_recovery.repository'),
            service('event_dispatcher'),
            service(UserRecoveryProvider::class),
        ])
        ->tag('flow.storer');

            $services->set(ScalarValuesStorer::class)
        ->tag('flow.storer');

    $services->set(MessageStorer::class)
        ->tag('flow.storer');

    $services->set(CustomAppStorer::class)
        ->tag('flow.storer', ['priority' => 999]);

    $services->set(LanguageStorer::class)
        ->tag('flow.storer');

    $services->set(TimezoneStorer::class)
        ->args([
            service(RequestStack::class),
        ])
        ->tag('flow.storer');

    $services->set(FlowFactory::class)
        ->public()
        ->args([
            tagged_iterator('flow.storer'),
        ]);











    $services->set(FlowTemplateDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(FlowTemplateConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(TriggerFlowController::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service('app_flow_event.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
