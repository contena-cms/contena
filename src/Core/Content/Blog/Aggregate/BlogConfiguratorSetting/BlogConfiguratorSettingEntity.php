<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting;

use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogConfiguratorSettingEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $blogId;

    protected string $optionId;

    protected ?string $mediaId = null;

    protected int $position;

    /**
     * @var array<Price>|null
     */
    protected ?array $price = null;

    protected ?PropertyGroupOptionEntity $option = null;

    protected ?MediaEntity $media = null;

    protected bool $selected = false;

    protected ?BlogEntity $blog = null;

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function setOptionId(string $optionId): void
    {
        $this->optionId = $optionId;
    }

    /**
     * @return array<Price>|null
     */
    public function getPrice(): ?array
    {
        return $this->price;
    }

    /**
     * @param array<Price> $price
     */
    public function setPrice(array $price): void
    {
        $this->price = $price;
    }

    public function getOption(): ?PropertyGroupOptionEntity
    {
        return $this->option;
    }

    public function setOption(PropertyGroupOptionEntity $option): void
    {
        $this->option = $option;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }
}
