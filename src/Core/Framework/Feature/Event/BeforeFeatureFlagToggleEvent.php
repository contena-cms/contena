<?php declare(strict_types=1);

namespace Contena\Core\Framework\Feature\Event;

use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class BeforeFeatureFlagToggleEvent extends Event
{
    public function __construct(
        public readonly string $feature,
        public readonly bool $active
    ) {
    }
}
