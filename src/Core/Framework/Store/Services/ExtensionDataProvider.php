<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Store\Struct\ExtensionCollection;

/**
 * @internal
 */
class ExtensionDataProvider extends AbstractExtensionDataProvider
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepository
     */
    public function __construct(
        private readonly ExtensionLoader $extensionLoader,
        private readonly EntityRepository $pluginRepository,
    ) {
    }

    public function getInstalledExtensions(Context $context, bool $loadCloudExtensions = true, ?Criteria $searchCriteria = null): ExtensionCollection
    {
        $criteria = $searchCriteria ? clone $searchCriteria : new Criteria();
        $criteria->addAssociation('translations');

        $plugins = $this->pluginRepository->search($criteria, $context)->getEntities();

        return $this->extensionLoader->loadFromPluginCollection($context, $plugins);
    }

    protected function getDecorated(): AbstractExtensionDataProvider
    {
        throw new DecorationPatternException(self::class);
    }
}
