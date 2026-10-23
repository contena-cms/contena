<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation;

use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;

/**
 * @codeCoverageIgnore
 */
class BlogStreamTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected string $blogStreamId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?BlogStreamEntity $blogStream = null;

    public function getBlogStreamId(): string
    {
        return $this->blogStreamId;
    }

    public function setBlogStreamId(string $blogStreamId): void
    {
        $this->blogStreamId = $blogStreamId;
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

    public function getBlogStream(): ?BlogStreamEntity
    {
        return $this->blogStream;
    }

    public function setBlogStream(?BlogStreamEntity $blogStream): void
    {
        $this->blogStream = $blogStream;
    }
}
