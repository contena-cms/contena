<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Content\Flow\Aggregate\FlowTemplate\FlowTemplateDefinition;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\AssignUserStatusAction;
use Contena\Core\Content\Flow\Dispatching\Action\CreateNotificationAction;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\Action\StopFlowAction;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutionTriggersListener;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowQueue;
use Contena\Core\Content\Flow\Dispatching\CachedFlowLoader;
use Contena\Core\Content\Flow\Dispatching\FlowDispatcher;
use Contena\Core\Content\Flow\Dispatching\FlowExecutor;
use Contena\Core\Content\Flow\Dispatching\FlowFactory;
use Contena\Core\Content\Flow\Dispatching\FlowLoader;
use Contena\Core\Content\Flow\Dispatching\Storer\BlogStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\ChannelContextStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\LanguageStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberGroupStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberRecoveryStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MemberStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\MessageStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\TimezoneStorer;
use Contena\Core\Content\Flow\Dispatching\Storer\UserStorer;
use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Content\Flow\Indexing\FlowBuilder;
use Contena\Core\Content\Flow\Indexing\FlowIndexer;
use Contena\Core\Content\Flow\Indexing\FlowPayloadUpdater;
use Contena\Core\Content\Flow\Telemetry\FlowMetricsInstrumentor;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Shared\MailFlow\DataProvider\BlogProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberGroupProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\MemberRecoveryProvider;
use Contena\Core\Content\Shared\MailFlow\DataProvider\UserRecoveryProvider;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FlowDefinition::class)->tag('contena.entity.definition');
    $services->set(FlowSequenceDefinition::class)->tag('contena.entity.definition');
    $services->set(FlowTemplateDefinition::class)->tag('contena.entity.definition');
    $services->set(FlowTemplateConfigFieldSerializer::class)->parent('Contena\\Core\\Framework\\DataAbstractionLayer\\FieldSerializer\\JsonFieldSerializer')->tag('contena.field_serializer');

    $services->set(FlowBuilder::class);
    $services->set(FlowPayloadUpdater::class)->args([service(Connection::class), service(FlowBuilder::class), service(CacheInvalidator::class)]);
    $services->set(FlowIndexer::class)->args([
        service(IteratorFactory::class), service('flow.repository'), service(FlowPayloadUpdater::class), service('event_dispatcher'),
    ])->tag('contena.entity_indexer');

    $services->set(FlowLoader::class)->args([service(Connection::class), service('logger')]);
    $services->set(CachedFlowLoader::class)->decorate(FlowLoader::class, null, -1000)->args([
        service(CachedFlowLoader::class . '.inner'), service('cache.object'),
    ])->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => 'reset']);

    $services->set(UserStorer::class)->args([service(UserRecoveryProvider::class)])->tag('flow.storer');
    $services->set(BlogStorer::class)->args([service(BlogProvider::class)])->tag('flow.storer');
    $services->set(MemberStorer::class)->args([service(MemberProvider::class)])->tag('flow.storer');
    $services->set(MemberGroupStorer::class)->args([service(MemberGroupProvider::class)])->tag('flow.storer');
    $services->set(MemberRecoveryStorer::class)->args([service(MemberRecoveryProvider::class)])->tag('flow.storer');
    $services->set(ChannelContextStorer::class)->args([service(AbstractChannelContextFactory::class)])->tag('flow.storer');
    $services->set(LanguageStorer::class)->tag('flow.storer');
    $services->set(MailStorer::class)->tag('flow.storer');
    $services->set(ScalarValuesStorer::class)->tag('flow.storer');
    $services->set(MessageStorer::class)->tag('flow.storer');
    $services->set(TimezoneStorer::class)->args([service(RequestStack::class)])->tag('flow.storer');
    $services->set(FlowFactory::class)->args([tagged_iterator('flow.storer')]);

    $services->set(SendMailAction::class)->args([
        service(MailTemplateSendService::class), service('mail_template.repository'), service('user.repository'), service('logger'),
    ])->tag('flow.action', ['key' => SendMailAction::ACTION_NAME, 'priority' => 500]);
    $services->set(CreateNotificationAction::class)->args([
        service(NotificationService::class),
    ])->tag('flow.action', ['key' => CreateNotificationAction::ACTION_NAME, 'priority' => 400]);
    $services->set(AssignUserStatusAction::class)->args([
        service('user.repository'),
    ])->tag('flow.action', ['key' => AssignUserStatusAction::ACTION_NAME, 'priority' => 350]);
    $services->set(AddUserTagAction::class)->args([
        service('user.repository'),
    ])->tag('flow.action', ['key' => AddUserTagAction::ACTION_NAME, 'priority' => 300]);
    $services->set(RemoveUserTagAction::class)->args([
        service('user_tag.repository'),
    ])->tag('flow.action', ['key' => RemoveUserTagAction::ACTION_NAME, 'priority' => 290]);
    $services->set(SetUserCustomFieldAction::class)->args([
        service(Connection::class), service('user.repository'),
    ])->tag('flow.action', ['key' => SetUserCustomFieldAction::ACTION_NAME, 'priority' => 280]);
    $services->set(StopFlowAction::class)->tag('flow.action', ['key' => 'action.stop.flow', 'priority' => 1]);
    $services->set(FlowActionCollector::class)->args([
        tagged_iterator('flow.action'),
        service('event_dispatcher'),
    ]);

    $services->set(FlowExecutor::class)->args([
        service(AbstractRuleLoader::class), service(Connection::class), service(ExtensionDispatcher::class), service('logger'), tagged_iterator('flow.action', 'key'),
        service(FlowMetricsInstrumentor::class),
    ]);
    $services->set(TriggerGroupResolver::class);
    $services->set(FlowMetricsInstrumentor::class)->args([
        service(Meter::class), service(TriggerGroupResolver::class),
    ]);
    $services->set(BufferedFlowQueue::class);
    $services->set(BufferedFlowExecutor::class)->args([
        service(BufferedFlowQueue::class), service(FlowLoader::class), service(FlowFactory::class), service(FlowExecutor::class), service('logger'),
    ]);
    $services->set(BufferedFlowExecutionTriggersListener::class)->args([service(BufferedFlowExecutor::class), service(BufferedFlowQueue::class)])->tag('kernel.event_subscriber');
    $services->set(FlowDispatcher::class)->decorate('event_dispatcher', null, 1000)->args([
        service(FlowDispatcher::class . '.inner'), service(FlowFactory::class), service(BufferedFlowQueue::class),
    ]);
};
