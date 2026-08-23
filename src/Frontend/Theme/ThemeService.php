<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigLoader;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigResetEvent;
use Contena\Frontend\Theme\Exception\InvalidThemeConfigException;
use Contena\Frontend\Theme\Exception\ThemeConfigException;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\Message\CompileThemeMessage;
use Contena\Frontend\Theme\Validator\SCSSValidator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

class ThemeService implements ResetInterface
{
    public const string CONFIG_THEME_COMPILE_ASYNC = 'core.frontendSettings.asyncThemeCompilation';
    public const string STATE_NO_QUEUE = 'state-no-queue';

    /**
     * Context state opting a theme assignment into being applied only after the background
     * compilation finished, so the frontend keeps serving the current theme during a switch.
     */
    public const string STATE_DEFER_ASSIGNMENT = 'theme-defer-assignment';

    /**
     * System config key (per channel) holding the most recently requested theme of a deferred
     * switch, so a background compilation finishing out of order cannot reactivate an older choice.
     */
    public const string CONFIG_KEY_PENDING_THEME = 'frontend.pendingThemeAssignment';

    private bool $notified = false;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     * @param EntityRepository<EntityCollection<Entity>> $themeChannelRepository
     */
    public function __construct(
        private readonly FrontendPluginRegistry $extensionRegistry,
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $themeChannelRepository,
        private readonly ThemeCompilerInterface $themeCompiler,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly AbstractConfigLoader $configLoader,
        private readonly Connection $connection,
        private readonly SystemConfigService $configService,
        private readonly MessageBusInterface $messageBus,
        private readonly NotificationService $notificationService,
        private readonly ThemeMergedConfigBuilder $mergedConfigBuilder,
        private readonly ThemeRuntimeConfigService $themeRuntimeConfigService,
    ) {
    }

    /**
     * Only compiles a single theme/channel combination.
     * Use `compileThemeById` to compile all dependend channels
     */
    public function compileTheme(
        string $channelId,
        string $themeId,
        Context $context,
        ?FrontendPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): void {
        if ($this->isAsyncCompilation($context)) {
            $this->handleAsync($channelId, $themeId, $withAssets, $context);

            return;
        }

        $themeConfig = $this->configLoader->load($themeId, $context);
        $this->themeCompiler->compileTheme(
            $channelId,
            $themeId,
            $themeConfig,
            $configurationCollection ?? $this->extensionRegistry->getConfigurations(),
            $withAssets,
            $context
        );

        // Refresh the runtime config values when the static file loader is used.
        // The static file loader is only used for the compiled theme configuration;
        // the resolved values are still stored in the runtime config table.
        if (!$this->configLoader instanceof StaticFileConfigLoader) {
            $importMap = null;
            if ($this->themeCompiler instanceof ThemeCompiler) {
                $importMap = $this->themeCompiler->buildComponentImportMap(
                    $configurationCollection ?? $this->extensionRegistry->getConfigurations()
                );

                $importMap ??= ['imports' => []];
            }

            $this->themeRuntimeConfigService->refreshRuntimeConfig(
                $themeId,
                $themeConfig,
                $context,
                true,
                $configurationCollection,
                $importMap,
            );
        } else {
            $this->themeRuntimeConfigService->refreshConfigValues($themeId, $context);
        }
    }

    public function refreshThemeImportMap(
        string $channelId,
        string $themeId,
        Context $context,
        ?FrontendPluginConfigurationCollection $configurationCollection = null
    ): void {
        if ($this->configLoader instanceof StaticFileConfigLoader || !$this->themeCompiler instanceof ThemeCompiler) {
            return;
        }

        $configurationCollection ??= $this->extensionRegistry->getConfigurations();
        $themeConfig = $this->configLoader->load($themeId, $context);
        $importMap = $this->themeCompiler->buildComponentImportMap($configurationCollection) ?? ['imports' => []];

        $this->themeRuntimeConfigService->refreshRuntimeConfig(
            $themeId,
            $themeConfig,
            $context,
            false,
            $configurationCollection,
            $importMap,
        );
    }

    /**
     * Compiles all dependend channel/Theme combinations
     *
     * @return list<string>
     */
    public function compileThemeById(
        string $themeId,
        Context $context,
        ?FrontendPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): array {
        $mappings = $this->getThemeDependencyMapping($themeId);
        $compiledThemeIds = [];
        foreach ($mappings as $mapping) {
            $this->compileTheme(
                $mapping->getChannelId(),
                $mapping->getThemeId(),
                $context,
                $configurationCollection ?? $this->extensionRegistry->getConfigurations(),
                $withAssets
            );

            $compiledThemeIds[] = $mapping->getThemeId();
        }

        return $compiledThemeIds;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public function updateTheme(string $themeId, ?array $config, ?string $parentThemeId, Context $context): void
    {
        $criteria = new Criteria([$themeId])
            ->addAssociation('channels');

        $theme = $this->themeRepository->search($criteria, $context)->getEntities()->first();
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $data = ['id' => $themeId];
        if ($config) {
            foreach ($config as $key => $value) {
                $data['configValues'][$key] = $value;
            }
        }

        if ($parentThemeId) {
            $data['parentThemeId'] = $parentThemeId;
        }

        $themeConfig = $this->getPlainThemeConfiguration($themeId, $context);

        $validFields = [];
        if ($themeConfig && isset($themeConfig['fields'])) {
            $validFields = array_keys($themeConfig['fields']);
        }

        // Cleanup the config values to only include the fields that are defined in the base config.
        // This is necessary, because the theme config might change and fields could have been removed.
        if (\array_key_exists('configValues', $data)) {
            $data['configValues'] = array_intersect_key($data['configValues'], array_flip($validFields));
        }

        if (\array_key_exists('configValues', $data)) {
            $this->dispatcher->dispatch(new ThemeConfigChangedEvent($themeId, $data['configValues'], $context));
        }

        // This part is not executed if the theme was reset before, because the config values are then empty.
        if (\array_key_exists('configValues', $data) && $theme->getConfigValues()) {
            $submittedChanges = $data['configValues'];
            $currentConfig = $theme->getConfigValues();
            $data['configValues'] = array_replace_recursive($currentConfig, $data['configValues']);

            // Cleaning up the config values also here, because there might be removed fields in the existing config values in the database.
            $data['configValues'] = array_intersect_key($data['configValues'], array_flip($validFields));

            foreach ($submittedChanges as $key => $changes) {
                if (isset($changes['value']) && \is_array($changes['value']) && isset($currentConfig[(string) $key]) && \is_array($currentConfig[(string) $key])) {
                    $data['configValues'][$key]['value'] = array_unique($changes['value']);
                }
            }
        }

        $this->themeRepository->update([$data], $context);

        if ($theme->getChannels() === null) {
            // refresh runtime config here as theme will not be compiled later
            $this->themeRuntimeConfigService->refreshConfigValues($themeId, $context);

            return;
        }

        $this->compileThemeById($themeId, $context, null, false);
    }

    public function assignTheme(string $themeId, string $channelId, Context $context, bool $skipCompile = false): bool
    {
        // Mark the requested theme as the channel's target for every switch, so a deferred compile
        // finishing out of order detects it was superseded (also by a synchronous switch) and the
        // admin can show an in-flight switch. On success it equals the now-live theme, so nothing
        // shows as pending; on failure it is restored so it never outlives the switch.
        $previousPendingTheme = $this->configService->getString(self::CONFIG_KEY_PENDING_THEME, $channelId);
        $this->configService->set(self::CONFIG_KEY_PENDING_THEME, $themeId, $channelId, false);

        try {
            // Deferred switch: apply the mapping only after compiling, so the frontend keeps
            // serving the current theme. Other callers apply synchronously.
            if (!$skipCompile && $context->hasState(self::STATE_DEFER_ASSIGNMENT) && $this->isAsyncCompilation($context)) {
                $this->handleAsync($channelId, $themeId, true, $context, true);

                return true;
            }

            RetryableTransaction::transactional($this->connection, function () use ($themeId, $channelId, $context, $skipCompile): void {
                if (!$skipCompile) {
                    $this->compileTheme($channelId, $themeId, $context);
                }

                $this->themeChannelRepository->upsert([[
                    'themeId' => $themeId,
                    'channelId' => $channelId,
                ]], $context);
            });

            $this->dispatcher->dispatch(new ThemeAssignedEvent($themeId, $channelId, $context));

            return true;
        } catch (\Throwable $e) {
            // The switch could not be started/applied: restore the marker, but only while it still
            // points at this request, so a newer request's marker is never clobbered.
            if ($this->configService->getString(self::CONFIG_KEY_PENDING_THEME, $channelId) === $themeId) {
                $this->configService->set(self::CONFIG_KEY_PENDING_THEME, $previousPendingTheme, $channelId, false);
            }

            throw $e;
        }
    }

    public function resetTheme(string $themeId, Context $context): void
    {
        $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->getEntities()->first();
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $data = ['id' => $themeId];
        $data['configValues'] = null;

        $this->dispatcher->dispatch(new ThemeConfigResetEvent($themeId, $context));

        $this->themeRepository->update([$data], $context);

        // Refresh runtime config after resetting theme config
        $this->themeRuntimeConfigService->refreshConfigValues($themeId, $context);
    }

    /**
     * Validates if the theme config can be compiled in SCSS.
     *
     * @param array<string, mixed> $config
     * @param array<int, string> $customAllowedRegex
     *
     * @return array<string, mixed>
     */
    public function validateThemeConfig(
        string $themeId,
        array $config,
        Context $context,
        array $customAllowedRegex = [],
        bool $sanitize = false
    ): array {
        // Get the merged theme config including inherited parent themes.
        $themeConfig = $this->getPlainThemeConfiguration($themeId, $context);

        // Single validation errors are collected in a wrapping exception.
        $themeConfigException = new ThemeConfigException();

        foreach ($config as $name => &$field) {
            // Lookup the field in the original theme config to get the field type.
            $fieldConfig = $themeConfig['fields'][$name] ?? null;

            // Skip fields that are not editable or excluded from SCSS compilation.
            if (!$fieldConfig
                || $fieldConfig['editable'] === false
                || $fieldConfig['scss'] === false) {
                continue;
            }

            $changedField = [
                'name' => $name,
                'value' => $field['value'],
                'type' => $fieldConfig['type'],
            ];

            try {
                $field['value'] = SCSSValidator::validate(
                    $this->scssCompiler,
                    $changedField,
                    $customAllowedRegex,
                    $sanitize
                );
            } catch (\Throwable $exception) {
                $themeConfigException->add($exception);
            }
        }

        // Check if there are any validation errors.
        $themeConfigException->tryToThrow();

        return $config;
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, mixed>
     */
    public function getPlainThemeConfiguration(string $themeId, Context $context): array
    {
        return $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getThemeConfigurationFieldStructure(string $themeId, Context $context): array
    {
        return $this->mergedConfigBuilder->getThemeConfigurationFieldStructure($themeId, $context);
    }

    public function getThemeDependencyMapping(string $themeId): ThemeChannelCollection
    {
        $mappings = new ThemeChannelCollection();
        $themeData = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(theme.id)) as id, LOWER(HEX(childTheme.id)) as dependentId,
            LOWER(HEX(tsc.channel_id)) as channelId,
            LOWER(HEX(dtsc.channel_id)) as dchannelId
            FROM theme
            LEFT JOIN theme as childTheme ON childTheme.parent_theme_id = theme.id
            LEFT JOIN theme_channel as tsc ON theme.id = tsc.theme_id
            LEFT JOIN theme_channel as dtsc ON childTheme.id = dtsc.theme_id
            WHERE theme.id = :id',
            ['id' => Uuid::fromHexToBytes($themeId)]
        );

        foreach ($themeData as $data) {
            if (isset($data['id']) && isset($data['channelId']) && $data['id'] === $themeId) {
                $mappings->add(new ThemeChannel($data['id'], $data['channelId']));
            }
            if (isset($data['dependentId']) && isset($data['dchannelId'])) {
                $mappings->add(new ThemeChannel($data['dependentId'], $data['dchannelId']));
            }
        }

        return $mappings;
    }

    public function reset(): void
    {
        $this->notified = false;
    }

    private function handleAsync(
        string $channelId,
        string $themeId,
        bool $withAssets,
        Context $context,
        bool $assign = false,
    ): void {
        $this->messageBus->dispatch(
            new CompileThemeMessage(
                $channelId,
                $themeId,
                $withAssets,
                $context,
                $assign,
            )
        );

        if ($this->notified !== true && $context->getScope() === Context::USER_SCOPE) {
            $this->notificationService->createNotification(
                [
                    'id' => Uuid::randomHex(),
                    'status' => 'info',
                    'message' => 'ct-theme-manager.detail.asyncCompilation.started',
                    'requiredPrivileges' => [],
                ],
                $context
            );
            $this->notified = true;
        }
    }

    private function isAsyncCompilation(Context $context): bool
    {
        if ($this->configLoader instanceof StaticFileConfigLoader) {
            return false;
        }

        return $this->configService->get(self::CONFIG_THEME_COMPILE_ASYNC) && !$context->hasState(self::STATE_NO_QUEUE);
    }
}
