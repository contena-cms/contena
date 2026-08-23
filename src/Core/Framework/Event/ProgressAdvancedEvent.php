<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProgressAdvancedEvent extends Event
{
    final public const string NAME = self::class;

    public function __construct(private readonly int $step = 1)
    {
    }

    public function getStep(): int
    {
        return $this->step;
    }
}
