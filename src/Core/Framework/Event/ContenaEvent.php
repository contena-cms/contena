<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;

interface ContenaEvent
{
    public function getContext(): Context;
}
