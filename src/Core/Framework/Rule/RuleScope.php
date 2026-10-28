<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Clock\Clock;

#[Package('fundamentals@after-sales')]
abstract class RuleScope
{
    abstract public function getContext(): Context;

    abstract public function getSalesChannelContext(): SalesChannelContext;

    public function getCurrentTime(): \DateTimeImmutable
    {
        return Clock::get()->now();
    }
}
