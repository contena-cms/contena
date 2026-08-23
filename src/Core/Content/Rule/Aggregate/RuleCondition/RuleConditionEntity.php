<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\Aggregate\RuleCondition;

use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\DataAbstractionLayer\Contract\IdAware;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class RuleConditionEntity extends Entity implements IdAware
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $type;

    protected string $ruleId;

    protected ?string $parentId = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $value = null;

    protected ?RuleEntity $rule = null;

    protected ?RuleConditionCollection $children = null;

    protected ?RuleConditionEntity $parent = null;

    protected int $position = 0;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getRuleId(): string
    {
        return $this->ruleId;
    }

    public function setRuleId(string $ruleId): void
    {
        $this->ruleId = $ruleId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getValue(): ?array
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed>|null $value
     */
    public function setValue(?array $value): void
    {
        $this->value = $value;
    }

    public function getRule(): ?RuleEntity
    {
        return $this->rule;
    }

    public function setRule(?RuleEntity $rule): void
    {
        $this->rule = $rule;
    }

    public function getChildren(): ?RuleConditionCollection
    {
        return $this->children;
    }

    public function setChildren(RuleConditionCollection $children): void
    {
        $this->children = $children;
    }

    public function getParent(): ?RuleConditionEntity
    {
        return $this->parent;
    }

    public function setParent(?RuleConditionEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}
