<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Maintenance;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;

#[Package('discovery')]
class MaintenancePage extends Page
{
    protected ?CmsPageEntity $cmsPage = null;

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }
}
