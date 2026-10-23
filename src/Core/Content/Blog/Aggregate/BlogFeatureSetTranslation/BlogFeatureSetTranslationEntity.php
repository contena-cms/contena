<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation;

use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetEntity;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogFeatureSetTranslationEntity extends TranslationEntity
{
    protected string $blogFeatureSetId;

    protected ?string $name = null;

    protected ?string $description = null;

    protected BlogFeatureSetEntity $blogFeatureSet;

    public function getBlogFeatureSetId(): string
    {
        return $this->blogFeatureSetId;
    }

    public function setBlogFeatureSetId(string $blogFeatureSetId): void
    {
        $this->blogFeatureSetId = $blogFeatureSetId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
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

    public function getBlogFeatureSet(): BlogFeatureSetEntity
    {
        return $this->blogFeatureSet;
    }

    public function setBlogFeatureSet(BlogFeatureSetEntity $blogFeatureSet): void
    {
        $this->blogFeatureSet = $blogFeatureSet;
    }
}
