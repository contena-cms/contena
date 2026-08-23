<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Subscriber;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Event\PluginLifecycleEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class PluginLifecycleSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FrontendPluginRegistry $frontendPluginRegistry,
        private readonly string $projectDirectory,
        private readonly AbstractFrontendPluginConfigurationFactory $pluginConfigurationFactory,
        private readonly ThemeLifecycleHandler $themeLifecycleHandler,
        private readonly ThemeLifecycleService $themeLifecycleService,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'pluginPostActivate',
            PluginPreUpdateEvent::class => 'pluginUpdate',
            PluginPostUpdateEvent::class => 'pluginPostUpdate',
            PluginPreDeactivateEvent::class => 'pluginPreDeactivate',
            PluginPostDeactivateEvent::class => 'pluginPostDeactivate',
            PluginPostDeactivationFailedEvent::class => 'pluginPostDeactivateFailed',
            PluginPreUninstallEvent::class => 'pluginPreUninstall',
            PluginPostUninstallEvent::class => 'pluginPostUninstall',
        ];
    }

    public function pluginPostActivate(PluginPostActivateEvent $event): void
    {
        $this->doPostActivate($event);
    }

    public function pluginPostDeactivateFailed(PluginPostDeactivationFailedEvent $event): void
    {
        $this->doPostActivate($event);
    }

    public function pluginUpdate(PluginPreUpdateEvent $event): void
    {
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $config = $this->frontendPluginRegistry->getConfigurations()->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $config,
            $this->frontendPluginRegistry->getConfigurations(),
            $event->getContext()->getContext()
        );
    }

    public function pluginPostUpdate(PluginPostUpdateEvent $event): void
    {
        if ($this->skipCompile($event->getContext()->getContext())) {
            return;
        }

        $this->refreshActiveThemeImportMaps(
            $event->getContext()->getContext(),
            $this->frontendPluginRegistry->getConfigurations(),
        );
    }

    public function pluginPostDeactivate(PluginPostDeactivateEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $frontendPluginConfigurations = $this->frontendPluginRegistry->getConfigurations();

        $this->refreshActiveThemeImportMaps($context, $frontendPluginConfigurations);

        $config = $frontendPluginConfigurations->getByTechnicalName($pluginName);

        if (!$config || !$config->hasAdditionalBundles()) {
            return;
        }

        $this->themeLifecycleHandler->recompileAllActiveThemes($context);
    }

    public function pluginPreDeactivate(PluginPreDeactivateEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $frontendPluginConfigurations = $this->frontendPluginRegistry->getConfigurations();

        $config = $frontendPluginConfigurations->getByTechnicalName($pluginName);

        if (!$config) {
            return;
        }

        if ($config->hasAdditionalBundles()) {
            $this->themeLifecycleHandler->deactivateTheme($config, $context);

            return;
        }

        $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
    }

    public function pluginPreUninstall(PluginPreUninstallEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $pluginName = $event->getPlugin()->getName();
        $frontendPluginConfigurations = $this->frontendPluginRegistry->getConfigurations();
        $filteredConfigurations = $frontendPluginConfigurations->filter(
            static fn (FrontendPluginConfiguration $registeredConfig): bool => $registeredConfig->getTechnicalName() !== $pluginName,
        );

        $config = $frontendPluginConfigurations->getByTechnicalName($pluginName);

        if ($config) {
            if ($config->hasAdditionalBundles()) {
                $this->themeLifecycleHandler->deactivateTheme($config, $context);
            } else {
                $this->themeLifecycleHandler->handleThemeUninstall($config, $context);
            }
        }

        $this->refreshActiveThemeImportMaps($context, $filteredConfigurations);
    }

    public function pluginPostUninstall(PluginPostUninstallEvent $event): void
    {
        if ($event->getContext()->keepUserData()) {
            return;
        }

        $this->themeLifecycleService->removeTheme($event->getPlugin()->getName(), $event->getContext()->getContext());
    }

    private function createConfigFromClassName(string $pluginPath, string $className): FrontendPluginConfiguration
    {
        $plugin = new $className(true, $pluginPath, $this->projectDirectory);

        if (!$plugin instanceof Plugin) {
            throw ThemeException::invalidPluginClass($plugin::class);
        }

        return $this->pluginConfigurationFactory->createFromBundle($plugin);
    }

    private function doPostActivate(PluginLifecycleEvent $event): void
    {
        if (!($event instanceof PluginPostActivateEvent) && !($event instanceof PluginPostDeactivationFailedEvent)) {
            return;
        }

        $context = $event->getContext()->getContext();

        if ($this->skipCompile($context)) {
            return;
        }

        $frontendPluginConfig = $this->createConfigFromClassName(
            $event->getPlugin()->getPath() ?: '',
            $event->getPlugin()->getBaseClass()
        );

        $configurationCollection = clone $this->frontendPluginRegistry->getConfigurations();
        $configurationCollection->add($frontendPluginConfig);

        $this->themeLifecycleHandler->handleThemeInstallOrUpdate(
            $frontendPluginConfig,
            $configurationCollection,
            $context
        );

        $this->refreshActiveThemeImportMaps($context, $configurationCollection);
    }

    private function skipCompile(Context $context): bool
    {
        return $context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
    }

    private function refreshActiveThemeImportMaps(
        Context $context,
        FrontendPluginConfigurationCollection $configurationCollection,
    ): void {
        $this->themeLifecycleHandler->refreshAllActiveThemeImportMaps($context, $configurationCollection);
    }
}
