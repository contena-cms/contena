<?php declare(strict_types=1);

namespace Contena\Core\System\TaxProvider\Aggregate\TaxProviderTranslation;

use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\TaxProvider\TaxProviderEntity;

/**
 * @codeCoverageIgnore
 */
class TaxProviderTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $taxProviderId;

    protected ?string $name = null;

    protected ?TaxProviderEntity $taxProvider = null;

    public function getTaxProviderId(): string
    {
        return $this->taxProviderId;
    }

    public function setTaxProviderId(string $taxProviderId): void
    {
        $this->taxProviderId = $taxProviderId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTaxProvider(): ?TaxProviderEntity
    {
        return $this->taxProvider;
    }

    public function setTaxProvider(?TaxProviderEntity $taxProvider): void
    {
        $this->taxProvider = $taxProvider;
    }
}
