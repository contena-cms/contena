<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Event;

use Symfony\Component\HttpFoundation\Request;

class InvalidateExpiredCacheRequestEvent
{
    /**
     * @internal Constructor for internal use only.
     */
    public function __construct(
        public readonly Request $request
    ) {
    }
}
