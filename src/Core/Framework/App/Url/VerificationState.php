<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Url;

/**
 * @internal
 */
readonly class VerificationState
{
    public function __construct(
        public VerificationStatus $status,
        public int $numTries,
        public \DateTimeImmutable $at,
        public ?string $info = null
    ) {
    }

    public function is(VerificationStatus $status): bool
    {
        return $this->status === $status;
    }

    public function isNotHardFail(): bool
    {
        return $this->status !== VerificationStatus::HARD_FAIL;
    }

    public function isInBackoff(\DateTimeImmutable $now, int $wait): bool
    {
        return $now->getTimestamp() < $this->at->getTimestamp() + $wait;
    }
}
