<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;

class ChannelCategoryEntity extends CategoryEntity
{
    protected ?string $seoUrl = null;

    protected ?BreadcrumbCollection $seoBreadcrumb = null;

    public function getSeoUrl(): ?string
    {
        return $this->seoUrl;
    }

    public function setSeoUrl(string $seoUrl): void
    {
        $this->seoUrl = $seoUrl;
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
