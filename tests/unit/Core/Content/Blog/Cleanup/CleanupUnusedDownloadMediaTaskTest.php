<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Cleanup\CleanupUnusedDownloadMediaTask;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CleanupUnusedDownloadMediaTask::class)]
class CleanupUnusedDownloadMediaTaskTest extends TestCase
{
    public function testGetTaskName(): void
    {
        static::assertSame('blog_download.media.cleanup', CleanupUnusedDownloadMediaTask::getTaskName());
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(2628000, CleanupUnusedDownloadMediaTask::getDefaultInterval());
    }
}
