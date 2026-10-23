<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation\ContentStreamTranslationDefinition;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamFilterChangeSetSubscriber;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamIndexer;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Content\ContentStream\ScheduledTask\UpdateBlogStreamMappingTask;
use Contena\Core\Content\ContentStream\ScheduledTask\UpdateBlogStreamMappingTaskHandler;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ContentStreamDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ContentStreamTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ContentStreamFilterDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ContentStreamBuilder::class)
        ->public()
        ->args([
            service('content_stream.repository'),
            service(BlogDefinition::class),
        ]);

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(ContentStreamBuilderInterface::class, ContentStreamBuilder::class)
        ->deprecate('contena/core', '6.8.0', 'The %alias_id% service is deprecated and will be removed in 6.8.0. Use Contena\Core\Content\ContentStream\Service\AbstractBlogStreamBuilder instead');

    $services->set(ContentStreamIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('content_stream.repository'),
            service('serializer'),
            service(BlogDefinition::class),
            service('event_dispatcher'),
        ])
        // Must run before BlogIndexer so it compiles stream filters before ContentStreamUpdater creates mappings.
        ->tag('contena.entity_indexer', ['priority' => 110]);

    $services->set(ContentStreamFilterChangeSetSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(UpdateBlogStreamMappingTask::class)
        ->tag('contena.scheduled.task');

    $services->set(UpdateBlogStreamMappingTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('content_stream.repository'),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');
};
