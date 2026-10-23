<?php declare(strict_types=1);

namespace Contena\Core\Service\Message;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @internal
 */
readonly class UpdateServiceMessage implements AsyncMessageInterface
{
    public function __construct(public string $name)
    {
    }
}
