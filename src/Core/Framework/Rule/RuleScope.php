<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Clock\Clock;

abstract class RuleScope
{
    abstract public function getContext(): Context;

    abstract public function getChannelContext(): ChannelContext;

    public function getCurrentTime(): \DateTimeImmutable
    {
        return Clock::get()->now();
    }
}
