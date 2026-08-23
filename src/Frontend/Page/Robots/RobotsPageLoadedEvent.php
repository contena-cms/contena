<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Component\HttpFoundation\Request;

class RobotsPageLoadedEvent extends NestedEvent implements ContenaEvent
{
    public function __construct(
        private readonly RobotsPage $page,
        private readonly Context $context,
        private readonly Request $request,
    ) {
    }

    public function getPage(): RobotsPage
    {
        return $this->page;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
