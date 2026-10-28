<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Request;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
readonly class SimulateRequest
{
    /**
     * @param array<string, string> $templateParts Associative array of mail template fields that should be rendered,
     *                                             e.g. subject, senderName, contentHtml, and contentPlain.
     */
    public function __construct(
        public array $templateParts,
        public string $eventName,
        public ?SalesChannelEntity $salesChannel = null,
        public bool $strictRendering = true,
    ) {
    }
}
