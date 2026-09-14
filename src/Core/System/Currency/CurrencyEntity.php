<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationCollection;

class CurrencyEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected float $factor;

    protected string $symbol;

    protected string $isoCode;

    protected ?string $shortName = null;

    protected ?string $name = null;

    protected int $position;

    protected int $decimalPrecision;

    protected ?CurrencyTranslationCollection $translations = null;

    public function getFactor(): float
    {
        return $this->factor;
    }

    public function setFactor(float $factor): void
    {
        $this->factor = $factor;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }

    public function setIsoCode(string $isoCode): void
    {
        $this->isoCode = $isoCode;
    }

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): void
    {
        $this->shortName = $shortName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDecimalPrecision(): int
    {
        return $this->decimalPrecision;
    }

    public function setDecimalPrecision(int $decimalPrecision): void
    {
        $this->decimalPrecision = $decimalPrecision;
    }

    public function getTranslations(): ?CurrencyTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}
