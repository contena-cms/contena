<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\InvalidatorStorage\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use Contena\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler;

/**
 * @internal
 */
#[CoversClass(CleanupOldCacheFoldersHandler::class)]
class CleanupOldCacheFoldersHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $cacheClearer = $this->createMock(CacheClearer::class);
        $cacheClearer->expects($this->once())->method('cleanupOldContainerCacheDirectories');

        $handler = new CleanupOldCacheFoldersHandler($cacheClearer);
        $handler(new CleanupOldCacheFolders());
    }
}
