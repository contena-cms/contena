<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Contena\Core\Framework\Context;

/**
 * @internal @codeCoverageIgnore
 */
readonly class BufferedFlow
{
    /**
     * @param array<string, mixed> $stored
     */
    public function __construct(public string $eventName, public Context $eventContext, public array $stored)
    {
    }
}
