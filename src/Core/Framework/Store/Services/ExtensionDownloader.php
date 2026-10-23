<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use GuzzleHttp\Exception\ClientException;

/**
 * @internal
 */
class ExtensionDownloader
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly EntityRepository $pluginRepository,
        private readonly StoreClient $storeClient,
        private readonly PluginManagementService $pluginManagementService,
    ) {
    }

    public function download(string $technicalName, Context $context): PluginDownloadDataStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $technicalName));

        $plugin = $this->pluginRepository->search($criteria, $context)->getEntities()->first();

        if ($plugin !== null && $plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw StoreException::cannotDeleteManaged($plugin->getName());
        }

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($technicalName, $context);
        } catch (ClientException $e) {
            throw StoreException::storeError($e);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        return $data;
    }
}
