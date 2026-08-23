<?php declare(strict_types=1);

namespace Contena\Frontend\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\Attribute\McpToolRequires;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Tool\McpToolResponse;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\ThemeService;

/**
 * This tool lives in the Frontend bundle because it depends on ThemeService,
 * which is a Frontend service. Placing it in Core/Framework would create an
 * inverted dependency (Core -> Frontend). The McpToolCompilerPass discovers
 * any service tagged mcp.tool regardless of bundle.
 */
#[McpTool(
    name: 'contena-theme-config',
    description: 'Read or update theme appearance settings (colors, logos, fonts) for a channel. Use action "get" to read the current theme config. Use action "update" with a config JSON to change values; dryRun=true (default) previews changes. channelId accepts either the channel UUID or its name as shown in the admin, e.g. "Web". See contena://channels for the full list.'
)]
#[McpToolGroup('theme')]
#[McpToolRequires('theme:read')]
#[McpToolRequires('theme:update')]
class ThemeConfigTool extends McpToolResponse
{
    /**
     * Upper bound for the channel names listed in a "not found" error.
     */
    private const int MAX_SUGGESTED_NAMES = 20;

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeService $themeService,
        private readonly McpContextProvider $contextProvider,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(
        string $channelId = '',
        string $action = 'get',
        string $config = '{}',
        bool $dryRun = true,
    ): string {
        if ($action !== 'get' && $action !== 'update') {
            return $this->error(\sprintf('Unknown action "%s". Use "get" or "update".', $action));
        }

        if ($channelId === '') {
            return $this->error('channelId is required. Use the contena://channels resource to find available channel IDs.');
        }

        $context = $this->contextProvider->getContext();

        $requiredPrivileges = $action === 'update'
            ? ['theme:read', 'theme:update']
            : ['theme:read'];

        if ($error = $this->requirePrivilege($context, ...$requiredPrivileges)) {
            return $error;
        }

        // Resolving runs after the privilege check so the error hints cannot enumerate channel
        // names. Infrastructure failures stay uncaught on purpose: per the McpToolResponse
        // contract only business errors become an error envelope.
        $resolved = $this->resolveChannelId($channelId);

        if (isset($resolved['error'])) {
            return $this->error($resolved['error']);
        }

        $themeId = $this->resolveThemeId($resolved['id']);

        if ($themeId === null) {
            return $this->error(\sprintf('No theme assigned to channel "%s".', $channelId));
        }

        return $action === 'get'
            ? $this->handleGet($themeId, $context)
            : $this->handleUpdate($themeId, $config, $dryRun, $context);
    }

    private function handleGet(string $themeId, Context $context): string
    {
        try {
            $configuration = $this->themeService->getPlainThemeConfiguration($themeId, $context);
        } catch (\Throwable $e) {
            return $this->error('Failed to read theme config: ' . $e->getMessage());
        }

        return $this->success([
            'themeId' => $themeId,
            'config' => $configuration,
        ]);
    }

    private function handleUpdate(string $themeId, string $configJson, bool $dryRun, Context $context): string
    {
        /** @var array<string, array{value: mixed}> $configValues */
        $configValues = $this->decodeJsonOrError($configJson, 'config');
        if (\is_string($configValues)) {
            return $configValues;
        }

        if ($configValues === []) {
            return $this->error('Config must be a non-empty JSON object with key-value pairs, e.g. {"ct-color-brand-primary": {"value": "#0000ff"}}');
        }

        if ($dryRun) {
            return $this->success([
                'themeId' => $themeId,
                'configToApply' => $configValues,
                'note' => 'Dry-run preview only. Config key names are not validated against the theme schema.',
            ], ['dryRun' => true]);
        }

        try {
            $this->themeService->updateTheme($themeId, $configValues, null, $context);
        } catch (\Throwable $e) {
            return $this->error('Theme update failed: ' . $e->getMessage());
        }

        return $this->success([
            'themeId' => $themeId,
            'updatedKeys' => array_keys($configValues),
        ], ['dryRun' => false]);
    }

    /**
     * Accepts either a channel UUID or its name, because agents typically know the name
     * shown in the admin, not the ID.
     *
     * @return array{id: string}|array{error: string}
     */
    private function resolveChannelId(string $input): array
    {
        $input = trim($input);
        $ids = $this->fetchChannelIds($input);

        if (\count($ids) === 1) {
            return ['id' => $ids[0]];
        }

        if ($ids === []) {
            return ['error' => \sprintf(
                'Channel "%s" not found. Available channels: %s. Pass one of these names or a channel UUID (see contena://channels).',
                $input,
                $this->listChannelNames(),
            )];
        }

        return ['error' => \sprintf(
            'Channel "%s" is ambiguous, %d channels match it. Pass one of these IDs instead: %s.',
            $input,
            \count($ids),
            implode(', ', $ids),
        )];
    }

    private function listChannelNames(): string
    {
        $names = $this->fetchChannelNames();

        if ($names === []) {
            return 'none';
        }

        $shown = \array_slice($names, 0, self::MAX_SUGGESTED_NAMES);
        $list = implode(', ', array_map(static fn (string $name): string => '"' . $name . '"', $shown));

        if (\count($names) > self::MAX_SUGGESTED_NAMES) {
            $list .= \sprintf(' and %d more', \count($names) - self::MAX_SUGGESTED_NAMES);
        }

        return $list;
    }

    /**
     * Matches the input against the ID and the name in one query so neither form shadows the
     * other. channel_translation.name uses utf8mb4_unicode_ci, so the name match is
     * case-insensitive through the column collation. DISTINCT collapses the one row per language
     * a single channel has.
     *
     * @return list<string>
     */
    private function fetchChannelIds(string $input): array
    {
        // Uuid::isValid() only matches lowercase hex, but agents copy uppercase IDs.
        $uuid = strtolower($input);

        return array_map(
            static fn (mixed $id): string => (string) $id,
            $this->connection->fetchFirstColumn(
                <<<'SQL'
                    SELECT DISTINCT LOWER(HEX(`c`.`id`))
                    FROM `channel` `c`
                    LEFT JOIN `channel_translation` `ct` ON `ct`.`channel_id` = `c`.`id`
                    WHERE `c`.`id` = :id OR `ct`.`name` = :name
                    SQL,
                [
                    // A non-UUID input binds NULL, which no ID can equal.
                    'id' => Uuid::isValid($uuid) ? Uuid::fromHexToBytes($uuid) : null,
                    'name' => $input,
                ],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function fetchChannelNames(): array
    {
        return array_map(
            static fn (mixed $name): string => (string) $name,
            $this->connection->fetchFirstColumn(
                'SELECT DISTINCT `name` FROM `channel_translation` WHERE `name` IS NOT NULL ORDER BY `name`',
            ),
        );
    }

    private function resolveThemeId(string $channelId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT LOWER(HEX(theme_id)) FROM theme_channel WHERE channel_id = :id',
            ['id' => Uuid::fromHexToBytes($channelId)],
        );

        return $result !== false ? (string) $result : null;
    }
}
