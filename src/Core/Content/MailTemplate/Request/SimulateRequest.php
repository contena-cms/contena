<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Request;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
readonly class SimulateRequest
{
    /**
     * @param array<string, string> $templateParts Associative array of mail template fields that should be rendered,
     *                                             e.g. subject, senderName, contentHtml, and contentPlain.
     */
    public function __construct(
        public array $templateParts,
        public string $eventName,
        public bool $strictRendering = true,
    ) {
    }
}
