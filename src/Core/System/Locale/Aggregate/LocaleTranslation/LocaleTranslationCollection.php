<?php declare(strict_types=1);

namespace Contena\Core\System\Locale\Aggregate\LocaleTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<LocaleTranslationEntity>
 */
class LocaleTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getLocaleIds(): array
    {
        return $this->fmap(static fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLocaleId());
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(static fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLocaleId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (LocaleTranslationEntity $localeTranslation) => $localeTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'locale_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationEntity::class;
    }
}
