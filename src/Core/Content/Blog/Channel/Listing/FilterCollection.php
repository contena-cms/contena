<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter as DALFilter;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Filter>
 */
class FilterCollection extends Collection
{
    /**
     * @param string|int $key
     * @param Filter|null $element
     */
    public function set($key, $element): void
    {
        if ($element === null) {
            return;
        }

        parent::set($key, $element);
    }

    /**
     * @param Filter $element
     */
    public function add($element): void
    {
        $this->validateType($element);

        $this->elements[$element->getName()] = $element;
    }

    public function blacklist(string $exclude): FilterCollection
    {
        $filtered = new self();
        foreach ($this->elements as $key => $value) {
            if ($exclude === $key) {
                continue;
            }
            $filtered->set($key, $value);
        }

        return $filtered;
    }

    public function filtered(): FilterCollection
    {
        return $this->filter(static fn (Filter $filter) => $filter->isFiltered());
    }

    /**
     * @return array<DALFilter>
     */
    public function getFilters(): array
    {
        return $this->fmap(static fn (Filter $filter) => $filter->getFilter());
    }

    protected function getExpectedClass(): ?string
    {
        return Filter::class;
    }
}
