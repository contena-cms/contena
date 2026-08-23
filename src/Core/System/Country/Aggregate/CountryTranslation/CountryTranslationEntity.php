<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Aggregate\CountryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Country\CountryEntity;

class CountryTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $countryId;

    protected ?string $name = null;

    protected ?CountryEntity $country = null;

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(CountryEntity $country): void
    {
        $this->country = $country;
    }
}
