<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Event\ExtensionLoadedEvent;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ExtensionLoader
{
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function loadFromPluginCollection(Context $context, PluginCollection $plugins): ExtensionCollection
    {
        $extensions = new ExtensionCollection();

        foreach ($plugins as $plugin) {
            try {
                $extension = $this->loadFromPlugin($context, $plugin);
                $extensions->set($extension->getName(), $extension);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to load plugin extension data', [
                    'plugin' => $plugin->getName(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $extensions;
    }

    private function loadFromPlugin(Context $context, PluginEntity $plugin): ExtensionStruct
    {
        $data = [
            'localId' => $plugin->getId(),
            'description' => $plugin->getTranslation('description'),
            'name' => $plugin->getName(),
            'label' => $plugin->getTranslation('label'),
            'producerName' => $plugin->getAuthor(),
            'license' => $plugin->getLicense(),
            'version' => $plugin->getVersion(),
            'latestVersion' => $plugin->getUpgradeVersion(),
            'iconRaw' => $plugin->getIcon(),
            'installedAt' => $plugin->getInstalledAt(),
            'active' => $plugin->getActive(),
            'type' => ExtensionStruct::EXTENSION_TYPE_PLUGIN,
            'isTheme' => false,
            'configurable' => $this->configurationService->checkConfiguration(\sprintf('%s.config', $plugin->getName()), $context),
            'updatedAt' => $plugin->getUpgradedAt(),
            'allowDisable' => true,
            'allowUpdate' => !$plugin->getManagedByComposer() || $plugin->isLocatedInCustomPluginDirectory(),
            'managedByComposer' => $plugin->getManagedByComposer(),
            'inAppPurchases' => [],
        ];

        $extension = ExtensionStruct::fromArray($data);

        $this->eventDispatcher->dispatch(new ExtensionLoadedEvent($plugin, $extension, $context));

        return $extension;
    }
}
