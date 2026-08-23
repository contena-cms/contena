<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation\Error;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Frontend\Page\Page;

class ErrorPage extends Page
{
    protected ?LandingPageEntity $landingPage = null;

    public function getLandingPage(): ?LandingPageEntity
    {
        return $this->landingPage;
    }

    public function setLandingPage(LandingPageEntity $landingPage): void
    {
        $this->landingPage = $landingPage;
    }

    public function isErrorPage(): bool
    {
        return true;
    }
}
