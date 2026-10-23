<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Aggregate\CurrencyCountryRounding;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CurrencyCountryRoundingEntity>
 *
 * @codeCoverageIgnore
 */
class CurrencyCountryRoundingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'currency_country_rounding_collection';
    }

    protected function getExpectedClass(): string
    {
        return CurrencyCountryRoundingEntity::class;
    }
}
