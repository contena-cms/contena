<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class MediaVisibilityRestrictionSubscriber implements EventSubscriberInterface, ResetInterface
{
    private const BLOG_DOWNLOAD_ENTITY = 'blog_download';

    private ?string $blogDownloadMediaFolderId = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => 'securePrivateFolders',
            BeforeEntityAggregationEvent::class => 'securePrivateMediaAggregation',
        ];
    }

    public function securePrivateFolders(EntitySearchedEvent $event): void
    {
        if ($this->isExplicitSystemScope($event->getContext())) {
            return;
        }

        match ($event->getDefinition()->getEntityName()) {
            MediaFolderDefinition::ENTITY_NAME => $this->addMediaFolderRestriction($event->getCriteria()),
            MediaDefinition::ENTITY_NAME => $this->addMediaRestriction($event->getCriteria()),
            default => null,
        };
    }

    public function securePrivateMediaAggregation(BeforeEntityAggregationEvent $event): void
    {
        if ($this->isExplicitSystemScope($event->getContext())) {
            return;
        }

        match ($event->getDefinition()->getEntityName()) {
            MediaFolderDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), $this->getMediaFolderRestriction()),
            MediaDefinition::ENTITY_NAME => $this->sanitizeAllAggregations($event->getCriteria(), $this->getMediaRestriction()),
            default => null,
        };
    }

    public function reset(): void
    {
        $this->blogDownloadMediaFolderId = null;
    }

    private function addMediaFolderRestriction(Criteria $criteria): void
    {
        $criteria->addFilter($this->getMediaFolderRestriction());
        $this->sanitizeAllAggregations($criteria, $this->getMediaFolderRestriction());
    }

    private function addMediaRestriction(Criteria $criteria): void
    {
        $criteria->addFilter($this->getMediaRestriction());

        $this->sanitizeAllAggregations($criteria, $this->getMediaRestriction());
    }

    private function sanitizeAllAggregations(Criteria $criteria, Filter $restrictionFilter): void
    {
        if ($criteria->getAggregations() === []) {
            return;
        }

        $saneAggregations = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $saneAggregations[] = $this->sanitizeAggregation($aggregation, $restrictionFilter);
        }
        $criteria->resetAggregations();
        $criteria->addAggregation(...$saneAggregations);
    }

    private function sanitizeAggregation(Aggregation $aggregation, Filter $restrictionFilter): Aggregation
    {
        return match ($aggregation::class) {
            FilterAggregation::class => $this->addRestrictionToFilterAggregation($aggregation, $restrictionFilter),
            default => $this->wrapAggregationWithRestriction($aggregation, $restrictionFilter),
        };
    }

    private function addRestrictionToFilterAggregation(FilterAggregation $aggregation, Filter $restrictionFilter): FilterAggregation
    {
        $aggregation->addFilters([$restrictionFilter]);

        return $aggregation;
    }

    private function wrapAggregationWithRestriction(Aggregation $aggregation, Filter $restrictionFilter): FilterAggregation
    {
        return new FilterAggregation(
            'Sanitized ' . $aggregation->getName(),
            $aggregation,
            [$restrictionFilter]
        );
    }

    private function getMediaRestriction(): Filter
    {
        $filters = [
            new EqualsFilter('private', false),
            new MultiFilter('AND', [
                new EqualsFilter('private', true),
                new EqualsFilter('mediaFolderId', $this->getBlogDownloadMediaFolderId()),
            ]),
        ];

        return new MultiFilter('OR', [
            ...$filters,
        ]);
    }

    private function getMediaFolderRestriction(): MultiFilter
    {
        return new MultiFilter('OR', [
            new EqualsFilter('media_folder.configuration.private', false),
            new EqualsFilter('media_folder.configuration.private', null),
        ]);
    }

    private function isExplicitSystemScope(Context $context): bool
    {
        return $context->getScope() === Context::SYSTEM_SCOPE
            && !$context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT);
    }

    private function getBlogDownloadMediaFolderId(): string
    {
        if ($this->blogDownloadMediaFolderId !== null) {
            return $this->blogDownloadMediaFolderId;
        }

        $folderId = $this->connection->fetchOne(
            <<<'SQL'
                SELECT LOWER(HEX(`media_folder`.`id`))
                FROM `media_folder`
                INNER JOIN `media_default_folder`
                    ON `media_default_folder`.`id` = `media_folder`.`default_folder_id`
                WHERE `media_default_folder`.`entity` = :entity
            SQL,
            ['entity' => self::BLOG_DOWNLOAD_ENTITY]
        );

        \assert(\is_string($folderId));

        return $this->blogDownloadMediaFolderId = $folderId;
    }
}
