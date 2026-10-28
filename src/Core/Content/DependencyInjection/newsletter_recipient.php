<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipientTag\NewsletterRecipientTagDefinition;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\NewsletterRecipientIndexer;
use Contena\Core\Content\Newsletter\Event\Subscriber\NewsletterRecipientDeletedSubscriber;
use Contena\Core\Content\Newsletter\NewsletterExceptionHandler;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Contena\Core\Content\Newsletter\SalesChannel\SalesChannelNewsletterRecipientDefinition;
use Contena\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTask;
use Contena\Core\Content\Newsletter\ScheduledTask\NewsletterRecipientTaskHandler;
use Contena\Core\Content\Newsletter\Subscriber\NewsletterRecipientSalutationSubscriber;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NewsletterRecipientDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelNewsletterRecipientDefinition::class)
        ->tag('contena.sales_channel.entity.definition');

    $services->set(NewsletterRecipientTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(NewsletterRecipientTask::class)
        ->tag('contena.scheduled.task');

    $services->set(NewsletterRecipientTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('newsletter_recipient.repository'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(NewsletterExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(NewsletterSubscribeRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('contena.rate_limiter'),
            service(RequestStack::class),
            service(StoreApiCustomFieldMapper::class),
            service('customer.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(NewsletterConfirmRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(NewsletterUnsubscribeRoute::class)
        ->public()
        ->args([
            service('newsletter_recipient.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(RateLimiter::class),
            service(RequestStack::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(NewsletterRecipientIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('newsletter_recipient.repository'),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer');

    $services->set(CustomerNewsletterSalesChannelsUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(NewsletterRecipientDeletedSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(NewsletterRecipientSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');
};
