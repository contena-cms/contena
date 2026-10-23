<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Frontend\Page\Page;

class BlogPage extends Page
{
    protected ChannelBlogEntity $blog;

    protected CmsPageEntity $cmsPage;

    protected ?string $navigationId = null;

    protected PropertyGroupCollection $configuratorSettings;

    protected PropertyGroupOptionCollection $selectedOptions;


    protected ?BreadcrumbCollection $breadcrumb = null;

    public function getBlog(): ChannelBlogEntity
    {
        return $this->blog;
    }

    public function setBlog(ChannelBlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getConfiguratorSettings(): PropertyGroupCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(PropertyGroupCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function getSelectedOptions(): PropertyGroupOptionCollection
    {
        return $this->selectedOptions;
    }

    public function setSelectedOptions(PropertyGroupOptionCollection $selectedOptions): void
    {
        $this->selectedOptions = $selectedOptions;
    }



    public function getBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(?BreadcrumbCollection $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getEntityName(): string
    {
        return BlogDefinition::ENTITY_NAME;
    }
}
