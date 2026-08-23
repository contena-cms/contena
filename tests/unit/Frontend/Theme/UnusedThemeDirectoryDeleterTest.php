<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\AbstractThemePathBuilder;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(UnusedThemeDirectoryDeleter::class)]
class UnusedThemeDirectoryDeleterTest extends TestCase
{
    public function testDeleteUnusedDirectories(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn([
            ['channelId' => 'channelId1', 'themeId' => 'themeId1'],
            ['channelId' => 'channelId2', 'themeId' => 'themeId1'],
        ]);

        $themeFileSystem = $this->createMock(FilesystemOperator::class);
        $themeFileSystem->expects($this->exactly(5))->method('listContents')->willReturnMap([
            ['theme', FilesystemReader::LIST_SHALLOW, new DirectoryListing([
                new DirectoryAttributes('theme/themeId1'),
                new DirectoryAttributes('theme/themeOldId'),
                new DirectoryAttributes('theme/usedThemePath'),
                new DirectoryAttributes('theme/unusedThemePathWithoutFiles'),
                new DirectoryAttributes('theme/unusedThemePathOlderThanOneDay'),
                new DirectoryAttributes('theme/unusedThemePathNewerThanOneDay'),
            ])],
            ['theme/unusedThemePathWithoutFiles', FilesystemReader::LIST_DEEP, new DirectoryListing([
                new DirectoryAttributes('theme/unusedThemePathWithoutFiles/foo'),
            ])],
            ['theme/unusedThemePathOlderThanOneDay', FilesystemReader::LIST_DEEP, new DirectoryListing([
                new FileAttributes('theme/unusedThemePathOlderThanOneDay/file1.txt', lastModified: new \DateTimeImmutable()->modify('-25 hours')->getTimestamp()),
            ])],
            ['theme/unusedThemePathNewerThanOneDay', FilesystemReader::LIST_DEEP, new DirectoryListing([
                new FileAttributes('theme/unusedThemePathNewerThanOneDay/file2.txt', lastModified: new \DateTimeImmutable()->modify('-23 hours')->getTimestamp()),
            ])],
            ['theme/themeOldId', FilesystemReader::LIST_DEEP, new DirectoryListing([
                new FileAttributes('theme/themeOldId/assets/file1.txt', lastModified: new \DateTimeImmutable()->modify('-25 hours')->getTimestamp()),
            ])],
        ]);
        $themeFileSystem->expects($this->exactly(3))->method('deleteDirectory')->willReturnMap([
            ['theme/unusedThemePathWithoutFiles'],
            ['theme/unusedThemePathOlderThanOneDay'],
            ['theme/themeOldId'],
        ]);

        $themePathBuilder = $this->createMock(AbstractThemePathBuilder::class);
        $themePathBuilder->expects($this->exactly(2))->method('assemblePath')->willReturnMap([
            ['channelId1', 'themeId1', 'usedThemePath'],
            ['channelId2', 'themeId1', 'differentThemePrefix'],
        ]);

        $deleter = new UnusedThemeDirectoryDeleter($connection, $themeFileSystem, $themePathBuilder, new NativeClock());

        static::assertSame(3, $deleter->deleteUnusedDirectories());
    }
}
