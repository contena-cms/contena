<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ProgressFinishedEvent extends Event
{
    final public const string NAME = self::class;

    public function __construct(private readonly string $message)
    {
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
