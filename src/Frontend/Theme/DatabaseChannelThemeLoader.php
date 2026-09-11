<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;

/**
 * @internal
 *
 * @final
 */
class DatabaseChannelThemeLoader
{
    /**
     * @var array<string, list<string>>
     */
    private array $themes = [];

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<string>
     */
    public function load(string $channelId): array
    {
        if (($this->themes[$channelId] ?? []) !== []) {
            return $this->themes[$channelId];
        }

        return $this->themes[$channelId] = $this->readFromDB($channelId);
    }

    public function reset(): void
    {
        $this->themes = [];
    }

    /**
     * @return list<string>
     */
    private function readFromDB(string $channelId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(theme.id)) AS themeId,
                    theme.technical_name AS technicalName,
                    LOWER(HEX(theme.parent_theme_id)) AS parentThemeId,
                    JSON_EXTRACT(theme.base_config, \'$.configInheritance\') AS configInheritance,
                    theme_channel.channel_id IS NOT NULL AS assigned
            FROM theme
                LEFT JOIN theme_channel
                    ON theme_channel.theme_id = theme.id
                    AND theme_channel.channel_id = :channelId',
            ['channelId' => Uuid::fromHexToBytes($channelId)]
        );

        $themesById = [];
        $idsByTechnicalName = [];
        $assignedThemeId = null;

        foreach ($rows as $row) {
            $themeId = (string) $row['themeId'];
            $themesById[$themeId] = $row;

            if (\is_string($row['technicalName'])) {
                $idsByTechnicalName[$row['technicalName']] = $themeId;
            }

            if ($assignedThemeId === null && (int) $row['assigned'] === 1) {
                $assignedThemeId = $themeId;
            }
        }

        if ($assignedThemeId === null) {
            return [];
        }

        $technicalNames = [];
        $visited = [$assignedThemeId => true];
        $queue = [$assignedThemeId];

        while (($themeId = array_shift($queue)) !== null) {
            $row = $themesById[$themeId];
            if (\is_string($row['technicalName'])) {
                $technicalNames[$row['technicalName']] = true;
            }

            foreach ($this->getAncestorIds($row, $idsByTechnicalName) as $ancestorId) {
                if (isset($visited[$ancestorId]) || !isset($themesById[$ancestorId])) {
                    continue;
                }

                $visited[$ancestorId] = true;
                $queue[] = $ancestorId;
            }
        }

        return array_keys($technicalNames);
    }

    /**
     * @return list<string>
     */
    private function getAncestorIds(array $row, array $idsByTechnicalName): array
    {
        $ancestorIds = [];
        if (\is_string($row['parentThemeId'])) {
            $ancestorIds[] = $row['parentThemeId'];
        }

        $configInheritance = json_decode((string) $row['configInheritance'], true);
        if (!\is_array($configInheritance)) {
            return $ancestorIds;
        }

        foreach (array_reverse($configInheritance) as $technicalName) {
            if (!\is_string($technicalName)) {
                continue;
            }

            $ancestorId = $idsByTechnicalName[ltrim($technicalName, '@')] ?? null;
            if ($ancestorId !== null && $ancestorId !== $row['themeId']) {
                $ancestorIds[] = $ancestorId;
            }
        }

        return $ancestorIds;
    }
}
