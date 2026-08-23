<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Frontend\Page\Page;

class BlogPage extends Page
{
    protected ChannelBlogEntity $blog;

    protected ?string $navigationId = null;

    protected ?BreadcrumbCollection $breadcrumb = null;

    public function getBlog(): ChannelBlogEntity
    {
        return $this->blog;
    }

    public function setBlog(ChannelBlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getNavigationId(): ?string
    {
        return $this->navigationId;
    }

    public function setNavigationId(?string $navigationId): void
    {
        $this->navigationId = $navigationId;
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
