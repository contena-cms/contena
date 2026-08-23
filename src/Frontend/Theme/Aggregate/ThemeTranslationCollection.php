<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Aggregate;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ThemeTranslationEntity>
 */
class ThemeTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string|null>
     */
    public function getThemeIds(): array
    {
        return $this->fmap(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getThemeId());
    }

    public function filterByThemeId(string $id): self
    {
        return $this->filter(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getThemeId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (ThemeTranslationEntity $themeTranslation) => $themeTranslation->getLanguageId() === $id);
    }

    protected function getExpectedClass(): string
    {
        return ThemeTranslationEntity::class;
    }
}
