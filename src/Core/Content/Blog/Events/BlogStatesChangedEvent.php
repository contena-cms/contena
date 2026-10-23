<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\DataAbstractionLayer\UpdatedStates;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
 */
class BlogStatesChangedEvent extends Event implements ContenaEvent
{
    /**
     * @param UpdatedStates[] $updatedStates
     */
    public function __construct(
        protected array $updatedStates,
        protected Context $context
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
     *
     * @return UpdatedStates[]
     */
    public function getUpdatedStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getUpdatedStates', 'v6.8.0.0')
        );

        return $this->updatedStates;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getContext', 'v6.8.0.0')
        );

        return $this->context;
    }
}
