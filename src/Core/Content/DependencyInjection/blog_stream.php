<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation\BlogStreamTranslationDefinition;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamFilterChangeSetSubscriber;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexer;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Content\BlogStream\Service\BlogStreamBuilder;
use Contena\Core\Content\BlogStream\Service\BlogStreamBuilderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Contena\Core\Content\BlogStream\ScheduledTask\UpdateBlogStreamMappingTask;
use Contena\Core\Content\BlogStream\ScheduledTask\UpdateBlogStreamMappingTaskHandler;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BlogStreamDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogStreamTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogStreamFilterDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(BlogStreamBuilder::class)
        ->public()
        ->args([
            service('blog_stream.repository'),
            service(BlogDefinition::class),
        ]);

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(BlogStreamBuilderInterface::class, BlogStreamBuilder::class)
        ->deprecate('contena/core', '6.8.0', 'The %alias_id% service is deprecated and will be removed in 6.8.0. Use Contena\Core\Content\BlogStream\Service\AbstractBlogStreamBuilder instead');

    $services->set(BlogStreamIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('blog_stream.repository'),
            service('serializer'),
            service(BlogDefinition::class),
            service('event_dispatcher'),
        ])
        // Must run before BlogIndexer so it compiles stream filters before BlogStreamUpdater creates mappings.
        ->tag('contena.entity_indexer', ['priority' => 110]);

    $services->set(BlogStreamFilterChangeSetSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(UpdateBlogStreamMappingTask::class)
        ->tag('contena.scheduled.task');

    $services->set(UpdateBlogStreamMappingTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('blog_stream.repository'),
            service('messenger.default_bus'),
        ])
        ->tag('messenger.message_handler');
};
