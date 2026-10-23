<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingAssignedBlogsEntity extends Entity
{
    use EntityIdTrait;

    protected string $crossSellingId;

    protected string $blogId;

    protected ?BlogEntity $blog = null;

    protected ?BlogCrossSellingEntity $crossSelling = null;

    protected int $position;

    public function getCrossSellingId(): string
    {
        return $this->crossSellingId;
    }

    public function setCrossSellingId(string $crossSellingId): void
    {
        $this->crossSellingId = $crossSellingId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(?BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getCrossSelling(): ?BlogCrossSellingEntity
    {
        return $this->crossSelling;
    }

    public function setCrossSelling(?BlogCrossSellingEntity $crossSelling): void
    {
        $this->crossSelling = $crossSelling;
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
