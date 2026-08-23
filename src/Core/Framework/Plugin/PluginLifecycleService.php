<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Composer\InstalledVersions;
use Composer\IO\NullIO;
use Composer\Semver\Comparator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Composer\CommandExecutor;
use Contena\Core\Framework\Plugin\Context\ActivateContext;
use Contena\Core\Framework\Plugin\Context\DeactivateContext;
use Contena\Core\Framework\Plugin\Context\InstallContext;
use Contena\Core\Framework\Plugin\Context\UninstallContext;
use Contena\Core\Framework\Plugin\Context\UpdateContext;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Contena\Core\Framework\Plugin\Exception\PluginHasActiveDependantsException;
use Contena\Core\Framework\Plugin\Exception\PluginNotActivatedException;
use Contena\Core\Framework\Plugin\Exception\PluginNotInstalledException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Contena\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;
use Contena\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\CustomField\CustomFieldXmlLoader;
use Contena\Core\System\CustomField\Xml\CustomFields;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;

/**
 * @internal
 */
class PluginLifecycleService
{
    final public const string STATE_SKIP_ASSET_BUILDING = 'skip-asset-building';
    final public const string PLUGIN_LIFECYCLE_METHOD_ACTIVATE = 'activate';

    /**
     * @var array{plugin: PluginEntity, context: Context}|null
     */
    private static ?array $pluginToBeDeleted = null;

    private static bool $registeredListener = false;

    /**
     * For `executeComposerRemoveCommand`, we need to keep the original event dispatcher, because during plugin
     * deactivation, the kernel is rebooted and the dispatcher replaced with the new one,
     * but the KernelEvents are triggered on the original event dispatcher.
     */
    private EventDispatcherInterface $originalEventDispatcher;

    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private EventDispatcherInterface $eventDispatcher,
        private readonly KernelPluginCollection $pluginCollection,
        private ContainerInterface $container,
        private readonly MigrationCollectionLoader $migrationLoader,
        private readonly AssetService $assetInstaller,
        private readonly CommandExecutor $executor,
        private readonly RequirementsValidator $requirementValidator,
        private readonly CacheItemPoolInterface $restartSignalCachePool,
        private readonly string $contenaVersion,
        private readonly SystemConfigService $systemConfigService,
        private readonly PluginService $pluginService,
        private readonly VersionSanitizer $versionSanitizer,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly RequestStack $requestStack,
        private readonly CustomFieldSetPersister $customFieldSetPersister,
        private readonly ClockInterface $clock,
    ) {
        $this->originalEventDispatcher = $eventDispatcher;
    }

    /**
     * @throws RequirementStackException
     */
    public function installPlugin(PluginEntity $plugin, Context $contenaContext): InstallContext
    {
        $pluginData = [];
        $pluginBaseClass = $this->getPluginBaseClass($plugin->getBaseClass());
        $pluginVersion = $plugin->getVersion();

        $installContext = new InstallContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $pluginVersion,
            $this->createMigrationCollection($pluginBaseClass)
        );

        if ($plugin->getInstalledAt()) {
            return $installContext;
        }

        $didRunComposerRequire = false;

        if ($pluginBaseClass->executeComposerCommands()) {
            $didRunComposerRequire = $this->executeComposerRequireWhenNeeded($plugin, $pluginBaseClass, $pluginVersion, $contenaContext);
        } else {
            $this->requirementValidator->validateRequirements($plugin, $contenaContext, 'install');
        }

        try {
            $pluginData['id'] = $plugin->getId();

            // Makes sure the version is updated in the db after a re-installation
            $updateVersion = $plugin->getUpgradeVersion();
            if ($updateVersion !== null && $this->hasPluginUpdate($updateVersion, $pluginVersion)) {
                $pluginData['version'] = $updateVersion;
                $plugin->setVersion($updateVersion);
                $pluginData['upgradeVersion'] = null;
                $plugin->setUpgradeVersion(null);
                $upgradeDate = $this->clock->now();
                $pluginData['upgradedAt'] = $upgradeDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
                $plugin->setUpgradedAt($upgradeDate);
            }

            $this->eventDispatcher->dispatch(new PluginPreInstallEvent($plugin, $installContext));

            $this->systemConfigService->savePluginConfiguration($pluginBaseClass, true);

            $pluginBaseClass->install($installContext);

            $this->runMigrations($installContext);

            $this->syncPluginCustomFields($pluginBaseClass, $contenaContext, false);

            $installDate = $this->clock->now();
            $pluginData['installedAt'] = $installDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $plugin->setInstalledAt($installDate);

            $this->updatePluginData($pluginData, $contenaContext);

            $pluginBaseClass->postInstall($installContext);

            $this->eventDispatcher->dispatch(new PluginPostInstallEvent($plugin, $installContext));
        } catch (\Throwable $e) {
            try {
                if ($didRunComposerRequire && $plugin->getComposerName() && !$this->container->getParameter('contena.deployment.cluster_setup')) {
                    $this->executor->remove($plugin->getComposerName(), $plugin->getName());
                }
            } finally {
                if ($plugin->getInstalledAt()) {
                    $this->uninstallPlugin($plugin, $contenaContext, true);
                }
            }

            throw $e;
        }

        return $installContext;
    }

    /**
     * @throws PluginNotInstalledException
     */
    public function uninstallPlugin(
        PluginEntity $plugin,
        Context $contenaContext,
        bool $keepUserData = false
    ): UninstallContext {
        if ($plugin->getInstalledAt() === null) {
            throw PluginException::notInstalled($plugin->getName());
        }

        if ($plugin->getActive()) {
            $this->deactivatePlugin($plugin, $contenaContext);
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $uninstallContext = new UninstallContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass),
            $keepUserData
        );
        $uninstallContext->setAutoMigrate(false);

        $this->eventDispatcher->dispatch(new PluginPreUninstallEvent($plugin, $uninstallContext));

        if (!$contenaContext->hasState(self::STATE_SKIP_ASSET_BUILDING)) {
            $this->assetInstaller->removeAssetsOfBundle($pluginBaseClassString);
        }

        if (!$uninstallContext->keepUserData()) {
            // plugin->uninstall() will remove the tables etc of the plugin,
            // we drop the migrations before, so we can recover in case of errors by rerunning the migrations
            $pluginBaseClass->removeMigrations();
        }

        $pluginBaseClass->uninstall($uninstallContext);

        if (!$uninstallContext->keepUserData()) {
            $this->systemConfigService->deletePluginConfiguration($pluginBaseClass);
        }

        $pluginId = $plugin->getId();
        $this->updatePluginData(
            [
                'id' => $pluginId,
                'active' => false,
                'installedAt' => null,
            ],
            $contenaContext
        );
        $plugin->setActive(false);
        $plugin->setInstalledAt(null);

        if (!$uninstallContext->keepUserData()) {
            $this->removePluginCustomFields($pluginBaseClass, $contenaContext);
        }

        if ($pluginBaseClass->executeComposerCommands()) {
            $this->executeComposerRemoveCommand($plugin, $contenaContext);
        }

        $this->eventDispatcher->dispatch(new PluginPostUninstallEvent($plugin, $uninstallContext));

        return $uninstallContext;
    }

    /**
     * @throws RequirementStackException
     */
    public function updatePlugin(PluginEntity $plugin, Context $contenaContext): UpdateContext
    {
        if ($plugin->getInstalledAt() === null) {
            throw PluginException::notInstalled($plugin->getName());
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $updateContext = new UpdateContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass),
            $plugin->getUpgradeVersion() ?? $plugin->getVersion()
        );

        if ($pluginBaseClass->executeComposerCommands()) {
            $this->executeComposerRequireWhenNeeded($plugin, $pluginBaseClass, $updateContext->getUpdatePluginVersion(), $contenaContext);
        } else {
            if ($plugin->getManagedByComposer() && $plugin->isLocatedInCustomDirectory()) {
                // If the plugin was previously managed by composer, but should no longer due to the update, we need to remove the composer dependency
                $this->executeComposerRemoveCommand($plugin, $contenaContext);
            }
            $this->requirementValidator->validateRequirements($plugin, $contenaContext, 'update');
        }

        $this->eventDispatcher->dispatch(new PluginPreUpdateEvent($plugin, $updateContext));

        $this->systemConfigService->savePluginConfiguration($pluginBaseClass);

        try {
            $pluginBaseClass->update($updateContext);
        } catch (\Throwable $updateException) {
            if ($plugin->getActive()) {
                try {
                    $this->deactivatePlugin($plugin, $contenaContext);
                } catch (\Throwable) {
                    $this->updatePluginData(
                        [
                            'id' => $plugin->getId(),
                            'active' => false,
                        ],
                        $contenaContext
                    );
                }
            }

            throw $updateException;
        }

        if ($plugin->getActive() && !$contenaContext->hasState(self::STATE_SKIP_ASSET_BUILDING)) {
            $this->assetInstaller->copyAssets($pluginBaseClass);
        }

        $this->runMigrations($updateContext);

        $this->syncPluginCustomFields($pluginBaseClass, $contenaContext, true);

        $updateVersion = $updateContext->getUpdatePluginVersion();
        $updateDate = $this->clock->now();
        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'version' => $updateVersion,
                'upgradeVersion' => null,
                'upgradedAt' => $updateDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            $contenaContext
        );
        $plugin->setVersion($updateVersion);
        $plugin->setUpgradeVersion(null);
        $plugin->setUpgradedAt($updateDate);

        $pluginBaseClass->postUpdate($updateContext);

        $this->eventDispatcher->dispatch(new PluginPostUpdateEvent($plugin, $updateContext));

        return $updateContext;
    }

    /**
     * @throws PluginNotInstalledException
     */
    public function activatePlugin(PluginEntity $plugin, Context $contenaContext, bool $reactivate = false, bool $validateRequirements = true): ActivateContext
    {
        if ($plugin->getInstalledAt() === null) {
            throw PluginException::notInstalled($plugin->getName());
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginBaseClass($pluginBaseClassString);

        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );

        if ($reactivate === false && $plugin->getActive()) {
            return $activateContext;
        }

        if ($validateRequirements === true) {
            $this->requirementValidator->validateRequirements($plugin, $contenaContext, self::PLUGIN_LIFECYCLE_METHOD_ACTIVATE);
        }

        $this->eventDispatcher->dispatch(new PluginPreActivateEvent($plugin, $activateContext));

        $plugin->setActive(true);

        // only skip rebuild if plugin has overwritten rebuildContainer method and source is system source (CLI)
        if ($pluginBaseClass->rebuildContainer() || !$contenaContext->getSource() instanceof SystemSource) {
            $this->rebuildContainerWithNewPluginState($plugin, $pluginBaseClass->getNamespace());
        }

        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);
        $activateContext = new ActivateContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );
        $activateContext->setAutoMigrate(false);

        $pluginBaseClass->activate($activateContext);

        $this->runMigrations($activateContext);

        if (!$contenaContext->hasState(self::STATE_SKIP_ASSET_BUILDING)) {
            $this->assetInstaller->copyAssets($pluginBaseClass);
        }

        $this->updatePluginData(
            [
                'id' => $plugin->getId(),
                'active' => true,
            ],
            $contenaContext
        );

        $this->signalWorkerStopInOldCacheDir();

        try {
            $this->eventDispatcher->dispatch(new PluginPostActivateEvent($plugin, $activateContext));
        } catch (\Throwable $exception) {
            $plugin->setActive(false);

            $this->updatePluginData(
                [
                    'id' => $plugin->getId(),
                    'active' => false,
                ],
                $contenaContext
            );

            throw $exception;
        }

        return $activateContext;
    }

    /**
     * @throws PluginNotInstalledException
     * @throws PluginNotActivatedException
     * @throws PluginHasActiveDependantsException
     */
    public function deactivatePlugin(PluginEntity $plugin, Context $contenaContext): DeactivateContext
    {
        if ($plugin->getInstalledAt() === null) {
            throw PluginException::notInstalled($plugin->getName());
        }

        if ($plugin->getActive() === false) {
            throw PluginException::notActivated($plugin->getName());
        }

        $dependantPlugins = array_values($this->getEntities($this->pluginCollection->all(), $contenaContext)->getEntities()->getElements());

        $dependants = $this->requirementValidator->resolveActiveDependants(
            $plugin,
            $dependantPlugins
        );

        if ($dependants !== []) {
            throw PluginException::hasActiveDependants($plugin->getName(), $dependants);
        }

        $pluginBaseClassString = $plugin->getBaseClass();
        $pluginBaseClass = $this->getPluginInstance($pluginBaseClassString);

        $deactivateContext = new DeactivateContext(
            $pluginBaseClass,
            $contenaContext,
            $this->contenaVersion,
            $plugin->getVersion(),
            $this->createMigrationCollection($pluginBaseClass)
        );
        $deactivateContext->setAutoMigrate(false);

        $this->eventDispatcher->dispatch(new PluginPreDeactivateEvent($plugin, $deactivateContext));

        try {
            $pluginBaseClass->deactivate($deactivateContext);

            if (!$contenaContext->hasState(self::STATE_SKIP_ASSET_BUILDING)) {
                $this->assetInstaller->removeAssetsOfBundle($plugin->getName());
            }

            $plugin->setActive(false);

            // only skip rebuild if plugin has overwritten rebuildContainer method and source is system source (CLI)
            if ($pluginBaseClass->rebuildContainer() || !$contenaContext->getSource() instanceof SystemSource) {
                $this->rebuildContainerWithNewPluginState($plugin, $pluginBaseClass->getNamespace());
            }

            $this->updatePluginData(
                [
                    'id' => $plugin->getId(),
                    'active' => false,
                ],
                $contenaContext
            );
        } catch (\Throwable $exception) {
            $activateContext = new ActivateContext(
                $pluginBaseClass,
                $contenaContext,
                $this->contenaVersion,
                $plugin->getVersion(),
                $this->createMigrationCollection($pluginBaseClass)
            );

            $this->eventDispatcher->dispatch(
                new PluginPostDeactivationFailedEvent(
                    $plugin,
                    $activateContext,
                    $exception
                )
            );

            throw $exception;
        }

        $this->signalWorkerStopInOldCacheDir();

        $this->eventDispatcher->dispatch(new PluginPostDeactivateEvent($plugin, $deactivateContext));

        return $deactivateContext;
    }

    /**
     * Only run composer remove as last thing in the request context,
     * as there might be some other event listeners that will break after the composer dependency is removed.
     *
     * This is not run on Kernel Terminate as this way we can give feedback to the user by letting the request fail,
     * if there is an issue with removing the composer dependency.
     */
    public function onResponse(): void
    {
        if (!self::$pluginToBeDeleted) {
            return;
        }

        $plugin = self::$pluginToBeDeleted['plugin'];
        $context = self::$pluginToBeDeleted['context'];
        self::$pluginToBeDeleted = null;

        $this->removePluginComposerDependency($plugin, $context);
    }

    /**
     * @internal only exists for overriding in tests
     */
    protected function isCLI(): bool
    {
        return \PHP_SAPI === 'cli';
    }

    private function removePluginComposerDependency(PluginEntity $plugin, Context $context): void
    {
        if ($this->container->getParameter('contena.deployment.cluster_setup')) {
            return;
        }

        $pluginComposerName = $plugin->getComposerName();
        if ($pluginComposerName === null) {
            throw PluginException::composerJsonInvalid(
                $plugin->getPath() . '/composer.json',
                ['No name defined in composer.json']
            );
        }

        $this->executor->remove($pluginComposerName, $plugin->getName());

        // running composer require may have consequences for other plugins, when they are required by the plugin being uninstalled
        $this->pluginService->refreshPlugins($context, new NullIO());
    }

    private function syncPluginCustomFields(Plugin $pluginBaseClass, Context $context, bool $deleteMissingXml): void
    {
        $xmlFile = $pluginBaseClass->getPath() . '/Resources/config/custom-fields.xml';

        if (!is_file($xmlFile)) {
            if ($deleteMissingXml) {
                $this->customFieldSetPersister->sync(CustomFields::fromArray([]), $pluginBaseClass->getName(), $context);
            }

            return;
        }

        $customFields = CustomFieldXmlLoader::load($xmlFile);

        $this->customFieldSetPersister->sync($customFields, $pluginBaseClass->getName(), $context);
    }

    private function removePluginCustomFields(Plugin $pluginBaseClass, Context $context): void
    {
        $this->customFieldSetPersister->sync(CustomFields::fromArray([]), $pluginBaseClass->getName(), $context);
    }

    private function getPluginBaseClass(string $pluginBaseClassString): Plugin
    {
        $baseClass = $this->pluginCollection->get($pluginBaseClassString);

        if ($baseClass === null) {
            throw PluginException::baseClassNotFound($pluginBaseClassString);
        }

        // set container because the plugin has not been initialized yet and therefore has no container set
        $baseClass->setContainer($this->container);

        return $baseClass;
    }

    private function createMigrationCollection(Plugin $pluginBaseClass): MigrationCollection
    {
        $migrationPath = str_replace(
            '\\',
            '/',
            $pluginBaseClass->getPath() . str_replace(
                $pluginBaseClass->getNamespace(),
                '',
                $pluginBaseClass->getMigrationNamespace()
            )
        );

        if (!is_dir($migrationPath)) {
            return $this->migrationLoader->collect('null');
        }

        $this->migrationLoader->addSource(new MigrationSource($pluginBaseClass->getName(), [
            $migrationPath => $pluginBaseClass->getMigrationNamespace(),
        ]));

        $collection = $this->migrationLoader
            ->collect($pluginBaseClass->getName());

        $collection->sync();

        return $collection;
    }

    private function runMigrations(InstallContext $context): void
    {
        if (!$context->isAutoMigrate()) {
            return;
        }

        $context->getMigrationCollection()->migrateInPlace();
    }

    private function hasPluginUpdate(string $updateVersion, string $currentVersion): bool
    {
        return version_compare($updateVersion, $currentVersion, '>');
    }

    /**
     * @param array<string, mixed|null> $pluginData
     */
    private function updatePluginData(array $pluginData, Context $context): void
    {
        $this->pluginRepo->update([$pluginData], $context);
    }

    private function rebuildContainerWithNewPluginState(PluginEntity $plugin, string $pluginNamespace): void
    {
        // Release session lock before container rebuild (to avoid holding file based session lock during long operation)
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession(true) && $request->getSession()->isStarted()) {
            $request->getSession()->save(); // Releases flock() on session file
        }

        $kernel = $this->container->get('kernel');

        $pluginDir = $kernel->getContainer()->getParameter('kernel.plugin_dir');
        if (!\is_string($pluginDir)) {
            throw PluginException::invalidContainerParameter('kernel.plugin_dir', 'string');
        }

        $pluginLoader = $this->container->get(KernelPluginLoader::class);

        $plugins = $pluginLoader->getPluginInfos();
        foreach ($plugins as &$pluginData) {
            if ($pluginData['baseClass'] === $plugin->getBaseClass()) {
                $pluginData['active'] = $plugin->getActive();
            }
        }
        unset($pluginData);

        if (!$plugin->getActive()) {
            $this->clearEntityExtensions($pluginNamespace);
        }

        /*
         * Reboot kernel with $plugin active=true.
         *
         * All other Requests won't have this plugin active until it's updated in the db
         */
        $tmpStaticPluginLoader = new StaticKernelPluginLoader($pluginLoader->getClassLoader(), $pluginDir, $plugins);
        $kernel->reboot(null, $tmpStaticPluginLoader);

        try {
            $newContainer = $kernel->getContainer();
        } catch (\LogicException) {
            // If symfony throws an exception when calling getContainer on a not booted kernel and catch it here
            throw PluginException::failedKernelReboot();
        }

        $this->container = $newContainer;
        $this->eventDispatcher = $newContainer->get('event_dispatcher');
    }

    private function clearEntityExtensions(string $pluginNamespace): void
    {
        if ($pluginNamespace === '') {
            return;
        }

        $definitions = $this->definitionRegistry->getDefinitions();
        foreach ($definitions as $definition) {
            $definition->removeExtensions($pluginNamespace);
        }
    }

    private function getPluginInstance(string $pluginBaseClassString): Plugin
    {
        if ($this->container->has($pluginBaseClassString)) {
            $containerPlugin = $this->container->get($pluginBaseClassString);
            if (!$containerPlugin instanceof Plugin) {
                throw PluginException::wrongBaseClass($pluginBaseClassString);
            }

            return $containerPlugin;
        }

        return $this->getPluginBaseClass($pluginBaseClassString);
    }

    private function signalWorkerStopInOldCacheDir(): void
    {
        $cacheItem = $this->restartSignalCachePool->getItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY);
        $cacheItem->set((float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT));
        $this->restartSignalCachePool->save($cacheItem);
    }

    /**
     * Takes plugin base classes and returns the corresponding entities.
     *
     * @param Plugin[] $plugins
     *
     * @return EntitySearchResult<PluginCollection>
     */
    private function getEntities(array $plugins, Context $context): EntitySearchResult
    {
        $names = array_map(static fn (Plugin $plugin) => $plugin->getName(), $plugins);

        return $this->pluginRepo->search(
            new Criteria()->addFilter(new EqualsAnyFilter('name', $names)),
            $context
        );
    }

    private function executeComposerRequireWhenNeeded(PluginEntity $plugin, Plugin $pluginBaseClass, string $pluginVersion, Context $contenaContext): bool
    {
        if ($this->container->getParameter('contena.deployment.cluster_setup')) {
            return false;
        }

        $pluginComposerName = $plugin->getComposerName();
        if ($pluginComposerName === null) {
            throw PluginException::composerJsonInvalid(
                $pluginBaseClass->getPath() . '/composer.json',
                ['No name defined in composer.json']
            );
        }

        try {
            $installedVersion = InstalledVersions::getVersion($pluginComposerName);
        } catch (\OutOfBoundsException) {
            // plugin is not installed using composer yet
            $installedVersion = null;
        }

        if ($installedVersion !== null) {
            $sanitizedVersion = $this->versionSanitizer->sanitizePluginVersion($installedVersion);

            if (Comparator::equalTo($sanitizedVersion, $pluginVersion)) {
                // plugin was already required at build time, no need to do so again at runtime
                return false;
            }
        }

        $this->executor->require($pluginComposerName . ':' . $pluginVersion, $plugin->getName());

        // running composer require may have consequences for other plugins, when they are required by the plugin being installed
        $this->pluginService->refreshPlugins($contenaContext, new NullIO());

        return true;
    }

    private function executeComposerRemoveCommand(PluginEntity $plugin, Context $contenaContext): void
    {
        if ($this->isCLI()) {
            // only remove the plugin composer dependency directly when running in CLI
            // otherwise do it async in kernel.response
            $this->removePluginComposerDependency($plugin, $contenaContext);
        } else {
            self::$pluginToBeDeleted = [
                'plugin' => $plugin,
                'context' => $contenaContext,
            ];

            if (!self::$registeredListener) {
                $this->originalEventDispatcher->addListener(KernelEvents::RESPONSE, $this->onResponse(...), \PHP_INT_MAX);
                self::$registeredListener = true;
            }
        }
    }
}
