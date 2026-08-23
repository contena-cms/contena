<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<StateMachineTranslationEntity>
 */
class StateMachineTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (StateMachineTranslationEntity $stateMachineTranslation) => $stateMachineTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'state_machine_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return StateMachineTranslationEntity::class;
    }
}
