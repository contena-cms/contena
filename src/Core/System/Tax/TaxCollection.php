<?php declare(strict_types=1);

namespace Contena\Core\System\Tax;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<TaxEntity>
 *
 * @codeCoverageIgnore
 */
class TaxCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tax_collection';
    }

    protected function getExpectedClass(): string
    {
        return TaxEntity::class;
    }
}
