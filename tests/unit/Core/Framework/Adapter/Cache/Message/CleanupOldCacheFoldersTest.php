<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use Contena\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CleanupOldCacheFolders::class)]
class CleanupOldCacheFoldersTest extends TestCase
{
    public function testDeduplicationId(): void
    {
        $message = new CleanupOldCacheFolders();
        static::assertSame('cleanup-old-cache-folders', $message->deduplicationId());
    }
}
