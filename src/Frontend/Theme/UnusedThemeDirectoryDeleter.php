<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\FilesystemReader;
use League\Flysystem\StorageAttributes;
use Psr\Clock\ClockInterface;

/**
 * Deletes theme directories that are no longer referenced by any channel/theme mapping.
 *
 * A directory is only removed when its files have not been modified within the grace period,
 * so that recently compiled themes still referenced by cached responses are kept long enough
 * to be served.
 *
 * @internal
 */
class UnusedThemeDirectoryDeleter
{
    private const GRACE_PERIOD_HOURS = 24;

    public function __construct(
        private readonly Connection $connection,
        private readonly FilesystemOperator $themeFileSystem,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return int the number of deleted theme directories
     */
    public function deleteUnusedDirectories(): int
    {
        $usedThemePaths = $this->getUsedThemePaths();

        $themeDirectories = $this->themeFileSystem->listContents('theme')->filter(function (StorageAttributes $themeDirectory) use ($usedThemePaths) {
            if (\in_array($themeDirectory->path(), $usedThemePaths, true)) {
                return false;
            }

            $modifiedTimestampOfFirstFile = $this->getModifiedTimestampOfFirstFile($themeDirectory);

            if ($modifiedTimestampOfFirstFile === null) {
                return true;
            }

            $graceBoundary = $this->clock->now()
                ->modify(\sprintf('-%d hours', self::GRACE_PERIOD_HOURS))
                ->getTimestamp();

            return $graceBoundary > $modifiedTimestampOfFirstFile;
        });

        $deletedCount = 0;
        foreach ($themeDirectories as $themeDirectory) {
            $this->themeFileSystem->deleteDirectory($themeDirectory->path());
            ++$deletedCount;
        }

        return $deletedCount;
    }

    /**
     * @return list<string>
     */
    private function getUsedThemePaths(): array
    {
        $channelThemeMappings = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(channel_id)) AS channelId, LOWER(HEX(theme_id)) AS themeId
             FROM theme_channel'
        );

        $themePaths = [];
        foreach (array_unique(array_column($channelThemeMappings, 'themeId')) as $themeId) {
            $themePaths[] = 'theme' . \DIRECTORY_SEPARATOR . $themeId;
        }

        foreach ($channelThemeMappings as $channelThemeMapping) {
            $themePaths[] = 'theme' . \DIRECTORY_SEPARATOR . $this->themePathBuilder->assemblePath(
                $channelThemeMapping['channelId'],
                $channelThemeMapping['themeId']
            );
        }

        return $themePaths;
    }

    private function getModifiedTimestampOfFirstFile(StorageAttributes $themeDirectory): ?int
    {
        foreach ($this->themeFileSystem->listContents($themeDirectory->path(), FilesystemReader::LIST_DEEP) as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $lastModified = $file->lastModified();
            if ($lastModified === null) {
                continue;
            }

            return $lastModified;
        }

        return null;
    }
}
