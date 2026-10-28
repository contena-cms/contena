<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ThemeEntity>
 */
#[Package('discovery')]
class ThemeCollection extends EntityCollection
{
    public function getByTechnicalName(string $technicalName): ?ThemeEntity
    {
        return $this->filter(static fn (ThemeEntity $theme) => $theme->getTechnicalName() === $technicalName)->first();
    }

    protected function getExpectedClass(): string
    {
        return ThemeEntity::class;
    }
}
