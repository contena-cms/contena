<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;

class LandingPageTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    protected ?string $tenantId = null;

    protected string $landingPageId;

    protected ?LandingPageEntity $landingPage = null;

    protected ?string $name = null;

    protected ?string $url = null;

    protected ?string $metaTitle = null;

    protected ?string $metaDescription = null;

    protected ?string $keywords = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getLandingPageId(): string
    {
        return $this->landingPageId;
    }

    public function setLandingPageId(string $landingPageId): void
    {
        $this->landingPageId = $landingPageId;
    }

    public function getLandingPage(): ?LandingPageEntity
    {
        return $this->landingPage;
    }

    public function setLandingPage(?LandingPageEntity $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): void
    {
        $this->url = $url;
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
}
