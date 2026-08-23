<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineState;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryCollection;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Contena\Core\System\StateMachine\StateMachineEntity;

class StateMachineStateEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $name = null;

    protected string $technicalName;

    protected string $stateMachineId;

    protected ?StateMachineEntity $stateMachine = null;

    protected ?StateMachineTransitionCollection $fromStateMachineTransitions = null;

    protected ?StateMachineTransitionCollection $toStateMachineTransitions = null;

    protected ?StateMachineStateTranslationCollection $translations = null;

    protected ?StateMachineHistoryCollection $fromStateMachineHistoryEntries = null;

    protected ?StateMachineHistoryCollection $toStateMachineHistoryEntries = null;

    public function getToStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->toStateMachineHistoryEntries;
    }

    public function setToStateMachineHistoryEntries(StateMachineHistoryCollection $toStateMachineHistoryEntries): void
    {
        $this->toStateMachineHistoryEntries = $toStateMachineHistoryEntries;
    }

    public function getFromStateMachineHistoryEntries(): ?StateMachineHistoryCollection
    {
        return $this->fromStateMachineHistoryEntries;
    }

    public function setFromStateMachineHistoryEntries(StateMachineHistoryCollection $fromStateMachineHistoryEntries): void
    {
        $this->fromStateMachineHistoryEntries = $fromStateMachineHistoryEntries;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getStateMachineId(): string
    {
        return $this->stateMachineId;
    }

    public function setStateMachineId(string $stateMachineId): void
    {
        $this->stateMachineId = $stateMachineId;
    }

    public function getStateMachine(): ?StateMachineEntity
    {
        return $this->stateMachine;
    }

    public function setStateMachine(StateMachineEntity $stateMachine): void
    {
        $this->stateMachine = $stateMachine;
    }

    public function getFromStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->fromStateMachineTransitions;
    }

    public function setFromStateMachineTransitions(StateMachineTransitionCollection $fromStateMachineTransitions): void
    {
        $this->fromStateMachineTransitions = $fromStateMachineTransitions;
    }

    public function getToStateMachineTransitions(): ?StateMachineTransitionCollection
    {
        return $this->toStateMachineTransitions;
    }

    public function setToStateMachineTransitions(StateMachineTransitionCollection $toStateMachineTransitions): void
    {
        $this->toStateMachineTransitions = $toStateMachineTransitions;
    }

    public function getTranslations(): ?StateMachineStateTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(StateMachineStateTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }
}
