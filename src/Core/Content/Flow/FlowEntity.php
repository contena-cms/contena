<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow;

use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceCollection;
use Contena\Core\Content\Flow\Dispatching\Struct\Flow;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class FlowEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $name;

    protected string $eventName;

    protected ?string $description = null;

    protected bool $active = false;

    protected int $priority = 1;

    protected string|Flow|null $payload = null;

    protected bool $invalid = false;

    protected ?FlowSequenceCollection $sequences = null;

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

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPayload(): string|Flow|null
    {
        $this->checkIfPropertyAccessIsAllowed('payload');

        return $this->payload;
    }

    public function setPayload(string|Flow|null $payload): void
    {
        $this->payload = $payload;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }

    public function getSequences(): ?FlowSequenceCollection
    {
        return $this->sequences;
    }

    public function setSequences(FlowSequenceCollection $sequences): void
    {
        $this->sequences = $sequences;
    }
}
