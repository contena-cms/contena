<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;

class ChannelBlogEntity extends BlogEntity
{
    protected ?CategoryEntity $seoCategory = null;

    protected ?BreadcrumbCollection $seoBreadcrumb = null;

    public function getSeoCategory(): ?CategoryEntity
    {
        return $this->seoCategory;
    }

    public function setSeoCategory(?CategoryEntity $category): void
    {
        $this->seoCategory = $category;
    }

    public function getSeoBreadcrumb(): ?BreadcrumbCollection
    {
        return $this->seoBreadcrumb;
    }

    public function setSeoBreadcrumb(?BreadcrumbCollection $seoBreadcrumb): void
    {
        $this->seoBreadcrumb = $seoBreadcrumb;
    }
}
