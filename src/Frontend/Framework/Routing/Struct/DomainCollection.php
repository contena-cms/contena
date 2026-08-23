<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * Keyed by the channel domain URL with a trailing slash (e.g. `https://example.com/de/`),
 * which matches the normalized request URL the RequestTransformer uses for lookups.
 *
 * @extends Collection<DomainStruct>
 */
class DomainCollection extends Collection
{
    /**
     * @param array<string, array<string, string|null>> $rows
     */
    public static function fromArray(array $rows): self
    {
        $collection = new self();

        foreach ($rows as $row) {
            $collection->add(DomainStruct::fromArray($row));
        }

        return $collection;
    }

    /**
     * @param DomainStruct $element
     */
    public function add($element): void
    {
        $this->set($element->url . '/', $element);
    }

    public function getApiAlias(): string
    {
        return 'frontend_domain_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return DomainStruct::class;
    }
}
