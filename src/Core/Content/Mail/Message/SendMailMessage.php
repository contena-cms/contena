<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Message;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 */
class SendMailMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(
        public readonly string $mailDataPath,
        public readonly ?string $tenantId,
    ) {
    }
}
