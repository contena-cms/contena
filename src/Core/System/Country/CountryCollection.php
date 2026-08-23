<?php declare(strict_types=1);

namespace Contena\Core\System\Country;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CountryEntity>
 */
class CountryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'country_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryEntity::class;
    }
}
