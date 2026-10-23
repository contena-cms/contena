<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Aggregate\CountryStateTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CountryStateTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class CountryStateTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getCountryStateIds(): array
    {
        return $this->fmap(static fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getCountryStateId());
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(static fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getCountryStateId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (CountryStateTranslationEntity $countryStateTranslation) => $countryStateTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'country_state_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationEntity::class;
    }
}
