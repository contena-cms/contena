<?php declare(strict_types=1);

namespace Contena\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Integration\IntegrationEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\System\StateMachine\StateMachineEntity;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @codeCoverageIgnore
 */
class StateMachineHistoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $stateMachineId;

    protected ?StateMachineEntity $stateMachine = null;

    protected string $entityName;

    protected string $referencedId;

    protected string $referencedVersionId;

    protected string $fromStateId;

    protected ?StateMachineStateEntity $fromStateMachineState = null;

    protected string $toStateId;

    protected ?StateMachineStateEntity $toStateMachineState = null;

    protected ?string $admin_userId = null;

    protected ?AdminUserEntity $admin_user = null;

    protected ?string $integrationId = null;

    protected ?IntegrationEntity $integration = null;

    protected string $transitionActionName;

    protected ?string $internalComment = null;

    protected ?string $sourceType = null;

    public function getTransitionActionName(): string
    {
        return $this->transitionActionName;
    }

    public function setTransitionActionName(string $transitionActionName): void
    {
        $this->transitionActionName = $transitionActionName;
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

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getReferencedId(): string
    {
        return $this->referencedId;
    }

    public function setReferencedId(string $referencedId): void
    {
        $this->referencedId = $referencedId;
    }

    public function getReferencedVersionId(): string
    {
        return $this->referencedVersionId;
    }

    public function setReferencedVersionId(string $referencedVersionId): void
    {
        $this->referencedVersionId = $referencedVersionId;
    }

    public function getFromStateId(): string
    {
        return $this->fromStateId;
    }

    public function setFromStateId(string $fromStateId): void
    {
        $this->fromStateId = $fromStateId;
    }

    public function getFromStateMachineState(): ?StateMachineStateEntity
    {
        return $this->fromStateMachineState;
    }

    public function getToStateId(): string
    {
        return $this->toStateId;
    }

    public function setToStateId(string $toStateId): void
    {
        $this->toStateId = $toStateId;
    }

    public function getToStateMachineState(): ?StateMachineStateEntity
    {
        return $this->toStateMachineState;
    }

    public function setToStateMachineState(StateMachineStateEntity $toStateMachineState): void
    {
        $this->toStateMachineState = $toStateMachineState;
    }

    public function getUserId(): ?string
    {
        return $this->admin_userId;
    }

    public function setUserId(?string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
    }

    public function getUser(): ?AdminUserEntity
    {
        return $this->admin_user;
    }

    public function setUser(?AdminUserEntity $admin_user): void
    {
        $this->admin_user = $admin_user;
    }

    public function setFromStateMachineState(StateMachineStateEntity $fromStateMachineState): void
    {
        $this->fromStateMachineState = $fromStateMachineState;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(?string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }

    public function getIntegration(): ?IntegrationEntity
    {
        return $this->integration;
    }

    public function setIntegration(?IntegrationEntity $integration): void
    {
        $this->integration = $integration;
    }

    public function getInternalComment(): ?string
    {
        return $this->internalComment;
    }

    public function setInternalComment(?string $internalComment): void
    {
        $this->internalComment = $internalComment;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }
}
