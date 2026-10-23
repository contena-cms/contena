<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Message;

use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class CleanupOldCacheFoldersHandler
{
    public function __construct(private CacheClearer $cacheClearer)
    {
    }

    public function __invoke(CleanupOldCacheFolders $message): void
    {
        $this->cacheClearer->cleanupOldContainerCacheDirectories();
    }
}
