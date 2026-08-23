<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Aggregate\FlowSequence;

use Contena\Core\Content\Flow\FlowEntity;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FlowSequenceEntity extends Entity implements IdAware
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $flowId;

    protected ?FlowEntity $flow = null;

    protected ?string $parentId = null;

    protected ?FlowSequenceEntity $parent = null;

    protected ?FlowSequenceCollection $children = null;

    protected ?string $ruleId = null;

    protected ?RuleEntity $rule = null;

    protected ?string $actionName = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected int $position = 1;

    protected int $displayGroup = 1;

    protected bool $trueCase = false;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function setFlowId(string $flowId): void
    {
        $this->flowId = $flowId;
    }

    public function getFlow(): ?FlowEntity
    {
        return $this->flow;
    }

    public function setFlow(FlowEntity $flow): void
    {
        $this->flow = $flow;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?FlowSequenceCollection
    {
        return $this->children;
    }

    public function setChildren(FlowSequenceCollection $children): void
    {
        $this->children = $children;
    }

    public function getRuleId(): ?string
    {
        return $this->ruleId;
    }

    public function setRuleId(?string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getRule(): ?RuleEntity
    {
        return $this->rule;
    }

    public function setRule(?RuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getActionName(): ?string
    {
        return $this->actionName;
    }

    public function setActionName(?string $actionName): void
    {
        $this->actionName = $actionName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDisplayGroup(): int
    {
        return $this->displayGroup;
    }

    public function setDisplayGroup(int $displayGroup): void
    {
        $this->displayGroup = $displayGroup;
    }

    public function isTrueCase(): bool
    {
        return $this->trueCase;
    }

    public function setTrueCase(bool $trueCase): void
    {
        $this->trueCase = $trueCase;
    }
}
