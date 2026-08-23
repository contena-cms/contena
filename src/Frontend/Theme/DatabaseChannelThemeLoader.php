<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Uuid\Uuid;

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

        return $this->readFromDB($channelId);
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
        $themes = $this->connection->fetchAssociative('
            SELECT LOWER(HEX(theme.id)) themeId, theme.technical_name as themeName, parentTheme.technical_name as parentThemeName, LOWER(HEX(parentTheme.parent_theme_id)) as grandParentThemeId
            FROM channel
                LEFT JOIN theme_channel ON channel.id = theme_channel.channel_id
                LEFT JOIN theme ON theme_channel.theme_id = theme.id
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE channel.id = :channelId
        ', [
            'channelId' => Uuid::fromHexToBytes($channelId),
        ]);

        if (\is_array($themes) && isset($themes['grandParentThemeId']) && \is_string($themes['grandParentThemeId'])) {
            $themes['grandParentNames'] = $this->getGrantParents($themes['grandParentThemeId']);
        }

        $usedThemes = array_filter([
            $themes['themeName'] ?? null,
            $themes['parentThemeName'] ?? null,
        ]);

        if (isset($themes['grandParentNames'])) {
            $usedThemes = array_merge($usedThemes, $themes['grandParentNames']);
        }

        return $this->themes[$channelId] = array_values($usedThemes) ?: [];
    }

    /**
     * @return list<string>
     */
    private function getGrantParents(mixed $grandParentThemeId): array
    {
        $grandParents = $this->connection->fetchAssociative('
            SELECT theme.technical_name as themeName, parentTheme.technical_name as parentThemeName, LOWER(HEX(parentTheme.parent_theme_id)) as grandParentThemeId
            FROM theme
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE theme.id = :id
        ', [
            'id' => Uuid::fromHexToBytes($grandParentThemeId),
        ]);

        $filtered = array_filter([
            $grandParents['themeName'] ?? null,
            $grandParents['parentThemeName'] ?? null,
        ]);

        if (\is_array($grandParents) && isset($grandParents['grandParentThemeId']) && \is_string($grandParents['grandParentThemeId'])) {
            $filtered = array_merge($filtered, $this->getGrantParents($grandParents['grandParentThemeId']));
        }

        return array_values($filtered);
    }
}
