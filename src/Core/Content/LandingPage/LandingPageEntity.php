<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Tag\TagCollection;

class LandingPageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected bool $active;

    protected ?LandingPageTranslationCollection $translations = null;

    protected ?TagCollection $tags = null;

    protected ?string $cmsPageId = null;

    protected ?string $cmsPageVersionId = null;

    protected ?CmsPageEntity $cmsPage = null;

    protected ?ChannelCollection $channels = null;

    protected ?string $name = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    protected ?string $url = null;

    /**
     * @var array<string, array<string, array<string, mixed>>|null>|null
     */
    protected ?array $slotConfig = null;

    protected ?SeoUrlCollection $seoUrls = null;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
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

    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(?string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
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

    /**
     * @return array<string, array<string, array<string, mixed>>|null>|null
     */
    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>|null>|null $slotConfig
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'slotConfig', newType: 'array', description: 'The parameter becomes required and non-nullable.')]
    public function setSlotConfig(?array $slotConfig): void
    {
        if ($slotConfig === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                '$slotConfig will be mandatory in future implementation'
            );
        }

        $this->slotConfig = $slotConfig;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getCmsPageVersionId(): ?string
    {
        return $this->cmsPageVersionId;
    }

    public function setCmsPageVersionId(?string $cmsPageVersionId): void
    {
        $this->cmsPageVersionId = $cmsPageVersionId;
    }
}
