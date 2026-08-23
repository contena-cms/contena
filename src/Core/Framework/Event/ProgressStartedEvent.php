<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProgressStartedEvent extends Event
{
    final public const string NAME = self::class;

    public function __construct(
        private readonly string $message,
        private readonly int $total
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
