<?php declare(strict_types=1);

namespace Contena\Core\Framework\Update\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
abstract class UpdateEvent extends Event
{
    public function __construct(private readonly Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
