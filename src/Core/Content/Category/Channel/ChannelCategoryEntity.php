<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryEntity;

class ChannelCategoryEntity extends CategoryEntity
{
    protected ?string $seoUrl = null;

    public function getSeoUrl(): ?string
    {
        return $this->seoUrl;
    }

    public function setSeoUrl(string $seoUrl): void
    {
        $this->seoUrl = $seoUrl;
    }
}
