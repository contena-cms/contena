<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Frontend\Page\Page;

class NavigationPage extends Page
{
    protected ?CategoryEntity $category = null;

    protected ?string $navigationId = null;

    protected ?BreadcrumbCollection $breadcrumb = null;

    protected ?BlogListingResult $listing = null;

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
    }

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
    }

    public function getBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(?BreadcrumbCollection $breadcrumb): void
    {
        $this->breadcrumb = $breadcrumb;
    }

    public function getListing(): ?BlogListingResult
    {
        return $this->listing;
    }

    public function setListing(?BlogListingResult $listing): void
    {
        $this->listing = $listing;
    }

    public function getEntityName(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }
}
