<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Message;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;
use Contena\Core\Framework\MessageQueue\DeduplicatableMessageInterface;

class CleanupOldCacheFolders implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    public function deduplicationId(): ?string
    {
        return 'cleanup-old-cache-folders';
    }
}
