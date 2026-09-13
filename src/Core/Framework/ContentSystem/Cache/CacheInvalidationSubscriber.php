<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Cache;

use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 *
 * @final
 */
#[AsEventListener(event: EntityWrittenContainerEvent::class)]
class CacheInvalidationSubscriber
{
    /**
     * @var array<string, ContentSection>
     */
    private readonly array $sectionAssignments;

    /**
     * @param array<string, string> $sectionAssignmentEntities assignment table name => ContentSection value
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly EntityCacheTagResolver $cacheTagResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        array $sectionAssignmentEntities = [],
    ) {
        $this->sectionAssignments = array_map(ContentSection::from(...), $sectionAssignmentEntities);
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        $this->invalidateContentLayout($event);
        $this->invalidateEntityContentLayout($event, BlogContentLayoutDefinition::ENTITY_NAME, 'blog_id', BlogDefinition::class);
        $this->invalidateEntityContentLayout($event, CategoryContentLayoutDefinition::ENTITY_NAME, 'category_id', CategoryDefinition::class);
        $this->invalidateEntityContentLayout($event, LandingPageContentLayoutDefinition::ENTITY_NAME, 'landing_page_id', LandingPageDefinition::class);

        foreach ($this->sectionAssignments as $entityName => $section) {
            $this->invalidateSectionContentLayout($event, $entityName, $section);
        }
    }

    private function invalidateContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys(ContentLayoutDefinition::ENTITY_NAME);

        if ($ids === []) {
            return;
        }

        $tags = array_map(
            static fn (string $id) => ContentSection::MAIN->buildLayoutTag($id),
            $ids
        );

        $this->cacheInvalidator->invalidate($tags);
    }

    /**
     * @param class-string $definitionClass
     */
    private function invalidateEntityContentLayout(
        EntityWrittenContainerEvent $event,
        string $entityName,
        string $column,
        string $definitionClass,
    ): void {
        $ids = $event->getPrimaryKeys($entityName);

        if ($ids === []) {
            return;
        }

        $entityIds = $this->fetchIdsFromAssignments($ids, $entityName, $column);

        if ($entityIds === []) {
            return;
        }

        $definition = $this->definitionRegistry->get($definitionClass);
        $tags = array_filter(array_map(
            fn (string $id) => $this->cacheTagResolver->resolve($definition, $id),
            $entityIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateSectionContentLayout(
        EntityWrittenContainerEvent $event,
        string $entityName,
        ContentSection $section,
    ): void {
        $ids = $event->getPrimaryKeys($entityName);

        if ($ids === []) {
            return;
        }

        $layoutIds = $this->fetchIdsFromAssignments($ids, $entityName, 'content_layout_id');

        if ($layoutIds === []) {
            return;
        }

        $tags = array_merge([], ...array_map(
            static fn (string $layoutId) => $section->buildRouteCacheTags($layoutId),
            $layoutIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function fetchIdsFromAssignments(array $assignmentIds, string $table, string $column): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(' . $column . ')) FROM ' . $table . ' WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
