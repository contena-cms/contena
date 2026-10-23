<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSellingTranslation;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingTranslationEntity extends TranslationEntity
{
    protected string $blogCrossSellingId;

    protected ?string $name = null;

    protected ?BlogCrossSellingEntity $blogCrossSelling = null;

    public function getBlogCrossSellingId(): string
    {
        return $this->blogCrossSellingId;
    }

    public function setBlogCrossSellingId(string $blogCrossSellingId): void
    {
        $this->blogCrossSellingId = $blogCrossSellingId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getBlogCrossSelling(): ?BlogCrossSellingEntity
    {
        return $this->blogCrossSelling;
    }

    public function setBlogCrossSelling(BlogCrossSellingEntity $blogCrossSelling): void
    {
        $this->blogCrossSelling = $blogCrossSelling;
    }
}
