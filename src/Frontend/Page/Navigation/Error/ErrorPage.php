<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation\Error;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Frontend\Page\Page;

class ErrorPage extends Page
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

    public function isErrorPage(): bool
    {
        return true;
    }
}
