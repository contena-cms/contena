<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

interface ContenaEvent
{
    public function getContext(): Context;
}
