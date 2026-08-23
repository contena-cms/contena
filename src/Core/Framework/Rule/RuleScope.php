<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Framework\Context;
use Symfony\Component\Clock\Clock;

/**
 * Base scope for rules that only need the generic application context.
 *
 * Plugins may extend this class to expose additional domain context without
 * coupling the core rule system to that domain.
 */
class RuleScope
{
    public function __construct(private readonly Context $context)
    {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCurrentTime(): \DateTimeImmutable
    {
        return Clock::get()->now();
    }
}
