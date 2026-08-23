<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Frontend\Page\Robots\Parser\ParseIssue;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when an unknown directive is encountered during robots.txt parsing.
 *
 * Allows developers to:
 * - Handle custom directives not in the standard set
 * - Prevent warnings for known-custom directives
 * - Set custom issues for specific directive types
 *
 *  Simple DTO with no business logic
 */
class RobotsUnknownDirectiveEvent extends Event implements ContenaEvent
{
    /**
     * Mark as true to prevent this directive from being logged as a warning.
     */
    public bool $handled = false;

    /**
     * Set a custom issue for this directive. If set, this issue will be used instead of the default warning.
     */
    public ?ParseIssue $issue = null;

    public function __construct(
        public readonly int $lineNumber,
        public readonly string $line,
        public readonly string $directiveType,
        public readonly string $directiveValue,
        public readonly bool $insideUserAgentBlock,
        public readonly Context $context,
        public readonly ?string $channelId = null
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
