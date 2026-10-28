<?php declare(strict_types=1);

namespace Contena\Storefront\Theme;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Storefront\Theme\ThemeRuntimeConfigStorageTest
 */
#[Package('discovery')]
class ThemeRuntimeConfigStorage
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }




    public function deleteByTechnicalName(string $technicalName): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `theme_runtime_config` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );
    }

    /**
     * @return array<string>
     */
    public function getActiveThemeNames(): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT DISTINCT `technical_name`
                FROM `theme_runtime_config`
                WHERE `technical_name` IS NOT NULL
            SQL,
        );
    }

    /**
     * Returns ids of theme copies (using the same theme implementation by duplicated config) for a given theme.
     *
     * @return array<string>
     */
    public function getCopiesIds(string $themeId): array
    {
        return $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT LOWER(HEX(`id`)) AS id
                FROM `theme`
                WHERE `parent_theme_id` = :themeId AND `technical_name` IS NULL
            SQL,
            ['themeId' => Uuid::fromHexToBytes($themeId)],
        );
    }

    /**
     * Returns ids of child themes and theme copies, recursively.
     *
     * @return array<string>
     */
    public function getChildThemeIds(string $parentThemeId): array
    {
        $processedThemeIds = [$parentThemeId];
        $childThemeIds = [];
        $pendingParentIds = [$parentThemeId];

        while ($pendingParentIds !== []) {
            $directChildren = $this->connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT LOWER(HEX(id)) as id FROM theme WHERE parent_theme_id IN (:parentIds)
                SQL,
                ['parentIds' => array_map(static fn ($id) => Uuid::fromHexToBytes($id), $pendingParentIds)],
                [
                    'parentIds' => ArrayParameterType::STRING,
                ]
            );

            $pendingParentIds = [];
            foreach ($directChildren as $childId) {
                $childId = (string) $childId;

                // Skip if we've already processed this theme (prevents infinite loops)
                if (\in_array($childId, $processedThemeIds, true)) {
                    continue;
                }

                $processedThemeIds[] = $childId;
                $childThemeIds[] = $childId;
                $pendingParentIds[] = $childId;
            }
        }

        return $childThemeIds;
    }

    public function getThemeTechnicalName(string $themeId): ?string
    {
        $names = $this->connection->fetchAssociative('
            SELECT theme.technical_name as themeName, parentTheme.technical_name as parentThemeName
            FROM theme
                LEFT JOIN theme AS parentTheme ON parentTheme.id = theme.parent_theme_id
            WHERE theme.id = :id
        ', [
            'id' => Uuid::fromHexToBytes($themeId),
        ]);

        if ($names === false) {
            return null;
        }

        return $names['themeName'] ?? $names['parentThemeName'] ?? null;
    }

    public function getThemeIdByTechnicalName(string $technicalName): ?string
    {
        $themeId = $this->connection->fetchOne('
            SELECT LOWER(HEX(id)) FROM theme WHERE technical_name = :technicalName
        ', [
            'technicalName' => $technicalName,
        ]);

        return $themeId === false ? null : $themeId;
    }

    /**
     * Returns the theme's own technical name (NULL for theme copies), unlike
     * {@see getThemeTechnicalName()} which falls back to the parent theme's technical name.
     */
    public function getOwnThemeTechnicalName(string $themeId): ?string
    {
        $technicalName = $this->connection->fetchOne(
            'SELECT technical_name FROM theme WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($themeId)]
        );

        return $technicalName === false ? null : $technicalName;
    }

}
