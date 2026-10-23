<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class SystemInstallCompletedEvent extends Event implements ContenaEvent
{
    public function __construct(private readonly Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
