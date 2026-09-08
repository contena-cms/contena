<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * The row before and after one health transition.
 *
 * @internal
 */
final readonly class HealthChange
{
    public function __construct(
        public HealthRow $from,
        public HealthRow $to,
    ) {
    }

    public function changedState(): bool
    {
        return $this->from->state !== $this->to->state;
    }
}
