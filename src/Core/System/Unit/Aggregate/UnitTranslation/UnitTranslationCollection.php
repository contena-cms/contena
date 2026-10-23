<?php declare(strict_types=1);

namespace Contena\Core\System\Unit\Aggregate\UnitTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UnitTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class UnitTranslationCollection extends EntityCollection
{
    /**
     * @return array<string, string>
     */
    public function getUnitIds(): array
    {
        return $this->fmap(static fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getUnitId());
    }

    public function filterByUnitId(string $id): self
    {
        return $this->filter(static fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getUnitId() === $id);
    }

    /**
     * @return array<string, string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (UnitTranslationEntity $unitTranslation) => $unitTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'unit_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return UnitTranslationEntity::class;
    }
}
