<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Message;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class SendMailMessage implements AsyncMessageInterface
{
    /**
     * @internal
     */
    public function __construct(public readonly string $mailDataPath)
    {
    }
}
