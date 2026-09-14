<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CurrencyEntity>
 */
class CurrencyCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyEntity::class;
    }
}
