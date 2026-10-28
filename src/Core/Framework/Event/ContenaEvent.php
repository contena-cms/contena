<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
interface ContenaEvent
{
    public function getContext(): Context;
}
