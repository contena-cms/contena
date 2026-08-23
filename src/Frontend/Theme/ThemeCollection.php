<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ThemeEntity>
 */
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
