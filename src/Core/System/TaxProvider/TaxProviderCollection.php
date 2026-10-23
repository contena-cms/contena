<?php declare(strict_types=1);

namespace Contena\Core\System\TaxProvider;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<TaxProviderEntity>
 */
class TaxProviderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_provider_collection';
    }

    public function sortByPriority(): void
    {
        $this->sort(static fn (TaxProviderEntity $a, TaxProviderEntity $b) => $b->getPriority() <=> $a->getPriority());
    }

    protected function getExpectedClass(): string
    {
        return TaxProviderEntity::class;
    }
}
