<?php declare(strict_types=1);

namespace Contena\Core\Content\Product;

use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Deprecation\BCChange\BecomesAbstract;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractPropertyGroupSorter
{
    abstract public function getDecorated(): AbstractPropertyGroupSorter;

    /**
     * @deprecated tag:v6.8.0 - Will be removed in v6.8.0. Use sortUsingLocaleCode() instead.
     *
     * @param EntityCollection<PropertyGroupOptionEntity|PartialEntity> $options
     */
    abstract public function sort(EntityCollection $options): PropertyGroupCollection;

    /**
     * @param EntityCollection<PropertyGroupOptionEntity|PartialEntity> $options
     */
    #[BecomesAbstract(version: 'v6.8.0')]
    public function sortUsingLocaleCode(EntityCollection $options, string $localeCode): PropertyGroupCollection
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'Method "sortUsingLocaleCode()" will become abstract in v6.8.0.0. Override it in %s, as the "sort()" method will be removed.',
                static::class
            )
        );

        return $this->sort($options);
    }
}
