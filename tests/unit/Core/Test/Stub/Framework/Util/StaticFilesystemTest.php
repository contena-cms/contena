<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Test\Stub\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;

/**
 * @internal
 */
#[CoversClass(StaticFilesystem::class)]
class StaticFilesystemTest extends TestCase
{
    public function testHas(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertTrue($fs->has('one.php'));
        static::assertFalse($fs->has('two.php'));
    }

    public function testPath(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame('/app-root/one.php', $fs->path('one.php'));
        static::assertSame('/app-root/two.php', $fs->path('two.php'));
    }

    public function testReadThrowsExceptionWhenFileDoesNotExist(): void
    {
        static::expectException(UtilException::class);

        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        $fs->read('two.php');
    }

    public function testRead(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame('content1', $fs->read('one.php'));
    }

    public function testFindFiles(): void
    {
        $fs = new StaticFilesystem([
            'one.php' => 'content1',
        ]);

        static::assertSame([], $fs->findFiles('*.php', '/'));
    }
}
