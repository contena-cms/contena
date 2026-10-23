<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Deprecation\BCChange\ClassHierarchyChange;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @extends EntitySearchResult<BlogCollection>
 */
#[ClassHierarchyChange(version: 'v6.8.0', description: 'Will no longer extend EntitySearchResult, but will keep extending Struct.', newParentClass: Struct::class)]
class BlogListingResult extends EntitySearchResult
{
    protected ?string $sorting = null;

    /**
     * @var array<string, int|float|string|bool|array<mixed>|null>
     */
    protected array $currentFilters = [];

    protected BlogSortingCollection $availableSortings;

    protected ?string $streamId = null;

    /**
     * Construction entry point with a stable signature across the v6.8.0 cut. Callers that adopt this method now will keep working after the structural change.
     *
     * @param EntitySearchResult<BlogCollection> $result
     * @param array<string, int|float|string|bool|array<mixed>|null> $currentFilters
     */
    public static function fromSearchResult(
        EntitySearchResult $result,
        ?BlogSortingCollection $availableSortings = null,
        ?string $sorting = null,
        array $currentFilters = [],
        ?string $streamId = null,
    ): self {
        $instance = self::createFrom($result);

        if ($availableSortings !== null) {
            $instance->availableSortings = $availableSortings;
        }
        $instance->sorting = $sorting;
        $instance->currentFilters = $currentFilters;
        $instance->streamId = $streamId;

        return $instance;
    }

    /**
     * Intentionally not deprecated, unlike the parent method: listing processors modify the page after construction by design.
     */
    public function setPage(int $page): void
    {
        /** @phpstan-ignore contena.futureIncompatibility.propertyBecomesReadonly (BlogListingResult no longer extends EntitySearchResult in v6.8.0, so this code path will be removed.) */
        $this->page = $page;
    }

    /**
     * Intentionally not deprecated, unlike the parent method: listing processors modify the limit after construction by design.
     */
    public function setLimit(int $limit): void
    {
        /** @phpstan-ignore contena.futureIncompatibility.propertyBecomesReadonly (BlogListingResult no longer extends EntitySearchResult in v6.8.0, so this code path will be removed.) */
        $this->limit = $limit;
    }

    /**
     * @param int|float|string|bool|array<mixed>|null $value
     */
    public function addCurrentFilter(string $key, $value): void
    {
        $this->currentFilters[$key] = $value;
    }

    public function getAvailableSortings(): BlogSortingCollection
    {
        return $this->availableSortings;
    }

    public function setAvailableSortings(BlogSortingCollection $availableSortings): void
    {
        $this->availableSortings = $availableSortings;
    }

    public function getSorting(): ?string
    {
        return $this->sorting;
    }

    public function setSorting(?string $sorting): void
    {
        $this->sorting = $sorting;
    }

    /**
     * @return array<string, int|float|string|bool|array<mixed>|null>
     */
    public function getCurrentFilters(): array
    {
        return $this->currentFilters;
    }

    /**
     * @return int|float|string|bool|array<mixed>|null
     */
    public function getCurrentFilter(string $key)
    {
        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'blog_listing';
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }
}
