<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter;

use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class ContentStreamFilterEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $type;

    protected ?string $field = null;

    protected ?string $operator = null;

    protected ?string $value = null;

    protected string $contentStreamId;

    protected ?string $parentId = null;

    protected ?ContentStreamEntity $contentStream = null;

    protected ?ContentStreamFilterCollection $queries = null;

    protected ?ContentStreamFilterEntity $parent = null;

    protected int $position;

    /**
     * @var array<string>|null
     */
    protected ?array $parameters = null;

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): void
    {
        $this->field = $field;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function getBlogStreamId(): string
    {
        return $this->contentStreamId;
    }

    public function setBlogStreamId(string $contentStreamId): void
    {
        $this->contentStreamId = $contentStreamId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getBlogStream(): ?ContentStreamEntity
    {
        return $this->contentStream;
    }

    public function setBlogStream(?ContentStreamEntity $contentStream): void
    {
        $this->contentStream = $contentStream;
    }

    public function getQueries(): ?ContentStreamFilterCollection
    {
        return $this->queries;
    }

    public function setQueries(ContentStreamFilterCollection $queries): void
    {
        $this->queries = $queries;
    }

    public function getParent(): ?ContentStreamFilterEntity
    {
        return $this->parent;
    }

    public function setParent(?ContentStreamFilterEntity $parent): void
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return array<string>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param array<string>|null $parameters
     */
    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }
}
