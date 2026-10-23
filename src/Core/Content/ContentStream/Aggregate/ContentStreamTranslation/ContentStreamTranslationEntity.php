<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Aggregate\ContentStreamTranslation;

use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;

/**
 * @codeCoverageIgnore
 */
class ContentStreamTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $contentStreamId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?ContentStreamEntity $contentStream = null;

    public function getBlogStreamId(): string
    {
        return $this->contentStreamId;
    }

    public function setBlogStreamId(string $contentStreamId): void
    {
        $this->contentStreamId = $contentStreamId;
    }

    public function getName(): ?string
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

    public function getBlogStream(): ?ContentStreamEntity
    {
        return $this->contentStream;
    }

    public function setBlogStream(?ContentStreamEntity $contentStream): void
    {
        $this->contentStream = $contentStream;
    }
}
