<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\SalesChannel;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelCategoryEntity extends CategoryEntity
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
