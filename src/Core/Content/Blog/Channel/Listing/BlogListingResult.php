<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\StateAwareTrait;
use Contena\Core\Framework\Struct\Struct;

class BlogListingResult extends Struct
{
    use StateAwareTrait;

    protected ?string $sorting = null;

    /**
     * @var array<string, int|float|string|bool|array<mixed>|null>
     */
    protected array $currentFilters = [];

    protected BlogSortingCollection $availableSortings;

    protected int $page;

    protected ?int $limit;

    private function __construct(
        protected readonly int $total,
        protected readonly BlogCollection $entities,
        protected readonly AggregationResultCollection $aggregations,
        protected readonly Criteria $criteria,
        protected readonly Context $context,
    ) {
        $this->limit = $criteria->getLimit();
        $this->page = !$this->limit ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $this->limit);
    }

    /**
     * @param EntitySearchResult<BlogCollection> $result
     * @param array<string, int|float|string|bool|array<mixed>|null> $currentFilters
     */
    public static function fromSearchResult(
        EntitySearchResult $result,
        ?BlogSortingCollection $availableSortings = null,
        ?string $sorting = null,
        array $currentFilters = [],
    ): self {
        $instance = new self(
            $result->getTotal(),
            $result->getEntities(),
            $result->getAggregations(),
            $result->getCriteria(),
            $result->getContext(),
        );

        $instance->availableSortings = $availableSortings ?? new BlogSortingCollection();
        $instance->sorting = $sorting;
        $instance->currentFilters = $currentFilters;
        $instance->addExtensions($result->getExtensions());
        $instance->addState(...$result->getStates());

        return $instance;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getEntities(): BlogCollection
    {
        return $this->entities;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param int|float|string|bool|array<mixed>|null $value
     */
    public function addCurrentFilter(string $key, mixed $value): void
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
    public function getCurrentFilter(string $key): mixed
    {
        return $this->currentFilters[$key] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'blog_listing';
    }
}
