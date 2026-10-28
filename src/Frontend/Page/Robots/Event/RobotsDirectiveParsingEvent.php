<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Robots\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Robots\Parser\ParsedRobots;

/**
 * Event dispatched after robots.txt content has been parsed.
 *
 * Allows developers to:
 * - Modify the parsed result (add/remove user-agent blocks, directives)
 * - Add custom validation and issues
 * - Transform directives based on custom logic
 */
#[Package('discovery')]
class RobotsDirectiveParsingEvent implements ContenaEvent
{
    public function __construct(
        public readonly string $text,
        public ParsedRobots $parsedResult,
        public readonly Context $context,
        public readonly ?string $salesChannelId = null
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
