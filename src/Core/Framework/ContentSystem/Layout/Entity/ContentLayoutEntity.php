<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Entity;

use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutCollection;
use Contena\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @final
 */
class ContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $tenantId = null;

    protected string $name;

    protected string $version;

    /**
     * @var list<ContentElement>
     */
    protected array $layout;

    // Always hydrated: root_source is a Required field, so every full load sets it. A future PartialEntity /
    // addFields reader that omits it would make getRootSource() throw on the uninitialized typed property.
    protected string $rootSource;

    protected ?BlogContentLayoutCollection $blogContentLayouts = null;

    protected ?CategoryContentLayoutCollection $categoryContentLayouts = null;

    protected ?LandingPageContentLayoutCollection $landingPageContentLayouts = null;

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function setTenantId(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @return list<ContentElement>
     */
    public function getLayout(): array
    {
        return $this->layout;
    }

    /**
     * @param list<ContentElement> $layout
     */
    public function setLayout(array $layout): void
    {
        $this->layout = $layout;
    }

    public function getRootSource(): string
    {
        return $this->rootSource;
    }

    public function setRootSource(string $rootSource): void
    {
        $this->rootSource = $rootSource;
    }

    public function getBlogContentLayouts(): ?BlogContentLayoutCollection
    {
        return $this->blogContentLayouts;
    }

    public function setBlogContentLayouts(BlogContentLayoutCollection $blogContentLayouts): void
    {
        $this->blogContentLayouts = $blogContentLayouts;
    }

    public function getCategoryContentLayouts(): ?CategoryContentLayoutCollection
    {
        return $this->categoryContentLayouts;
    }

    public function setCategoryContentLayouts(CategoryContentLayoutCollection $categoryContentLayouts): void
    {
        $this->categoryContentLayouts = $categoryContentLayouts;
    }

    public function getLandingPageContentLayouts(): ?LandingPageContentLayoutCollection
    {
        return $this->landingPageContentLayouts;
    }

    public function setLandingPageContentLayouts(LandingPageContentLayoutCollection $landingPageContentLayouts): void
    {
        $this->landingPageContentLayouts = $landingPageContentLayouts;
    }
}
