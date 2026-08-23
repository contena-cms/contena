<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Tag\TagCollection;

class LandingPageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected bool $active;

    protected ?LandingPageTranslationCollection $translations = null;

    protected ?TagCollection $tags = null;

    protected ?ChannelCollection $channels = null;

    protected ?string $name = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    protected ?string $url = null;

    protected ?SeoUrlCollection $seoUrls = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getTranslations(): ?LandingPageTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(LandingPageTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getChannels(): ?ChannelCollection
    {
        return $this->channels;
    }

    public function setChannels(ChannelCollection $channels): void
    {
        $this->channels = $channels;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }
}
