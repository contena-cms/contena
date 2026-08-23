<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeTranslationEntity>
 */
class NumberRangeTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getNumberRangeIds(): array
    {
        return $this->fmap(static fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getNumberRangeId());
    }

    public function filterByNumberRangeId(string $id): self
    {
        return $this->filter(static fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getNumberRangeId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (NumberRangeTranslationEntity $numberRangeTranslation) => $numberRangeTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'number_range_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeTranslationEntity::class;
    }
}
