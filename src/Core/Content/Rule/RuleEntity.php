<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Tag\TagCollection;

class RuleEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $name;

    protected ?string $description = null;

    protected int $priority;

    protected string|Rule|null $payload = null;

    protected ?RuleConditionCollection $conditions = null;

    protected bool $invalid = false;

    /**
     * @var list<string>|null
     */
    protected ?array $areas = null;

    protected ?FlowSequenceCollection $flowSequences = null;

    protected ?TagCollection $tags = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPayload(): string|Rule|null
    {
        $this->checkIfPropertyAccessIsAllowed('payload');

        return $this->payload;
    }

    public function setPayload(string|Rule|null $payload): void
    {
        $this->payload = $payload;
    }

    public function getConditions(): ?RuleConditionCollection
    {
        return $this->conditions;
    }

    public function setConditions(RuleConditionCollection $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    /**
     * @return list<string>|null
     */
    public function getAreas(): ?array
    {
        return $this->areas;
    }

    /**
     * @param list<string>|null $areas
     */
    public function setAreas(?array $areas): void
    {
        $this->areas = $areas;
    }

    public function getFlowSequences(): ?FlowSequenceCollection
    {
        return $this->flowSequences;
    }

    public function setFlowSequences(FlowSequenceCollection $flowSequences): void
    {
        $this->flowSequences = $flowSequences;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }
}
