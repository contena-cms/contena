<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\Element;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

/**
 * @implements \IteratorAggregate<array-key, EntitySearchResult<covariant EntityCollection<covariant Entity>>>
 */
class ElementDataCollection implements \IteratorAggregate, \Countable
{
    /**
     * @var array<string, EntitySearchResult<covariant EntityCollection<covariant Entity>>>
     */
    protected array $searchResults = [];

    /**
     * @param EntitySearchResult<covariant EntityCollection<covariant Entity>> $entitySearchResult
     */
    public function add(string $key, EntitySearchResult $entitySearchResult): void
    {
        $this->searchResults[$key] = $entitySearchResult;
    }

    /**
     * @return EntitySearchResult<covariant EntityCollection<covariant Entity>>|null
     */
    public function get(string $key): ?EntitySearchResult
    {
        return $this->searchResults[$key] ?? null;
    }

    /**
     * @return \Traversable<string, EntitySearchResult<covariant EntityCollection<covariant Entity>>>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->searchResults;
    }

    public function count(): int
    {
        return \count($this->searchResults);
    }
}
