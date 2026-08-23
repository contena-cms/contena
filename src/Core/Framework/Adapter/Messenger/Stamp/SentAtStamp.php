<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Messenger\Stamp;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Stamp\StampInterface;

readonly class SentAtStamp implements StampInterface
{
    private \DateTimeInterface $sentAt;

    public function __construct(?\DateTimeInterface $sentAt = null, ClockInterface $clock = new NativeClock())
    {
        $this->sentAt = $sentAt ?? $clock->now();
    }

    public function getSentAt(): \DateTimeInterface
    {
        return $this->sentAt;
    }
}
