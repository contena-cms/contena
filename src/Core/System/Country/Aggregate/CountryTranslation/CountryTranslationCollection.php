<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Aggregate\CountryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CountryTranslationEntity>
 */
class CountryTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (CountryTranslationEntity $countryTranslation) => $countryTranslation->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (CountryTranslationEntity $countryTranslation) => $countryTranslation->getCountryId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (CountryTranslationEntity $countryTranslation) => $countryTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (CountryTranslationEntity $countryTranslation) => $countryTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryTranslationEntity::class;
    }
}
