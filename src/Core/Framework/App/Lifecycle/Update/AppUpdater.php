<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Update;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Contena\Core\Framework\Store\Services\ExtensionDownloader;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;

/**
 * @internal
 */
class AppUpdater extends AbstractAppUpdater
{
    /**
     * @param EntityRepository<AppCollection> $appRepo
     */
    public function __construct(
        private readonly AbstractExtensionDataProvider $extensionDataProvider,
        private readonly EntityRepository $appRepo,
        private readonly ExtensionDownloader $downloader,
        private readonly AbstractStoreAppLifecycleService $appLifecycle
    ) {
    }

    public function updateApps(Context $context): void
    {
        $extensions = $this->extensionDataProvider->getInstalledExtensions($context, true);
        $extensions = $extensions->filterByType(ExtensionStruct::EXTENSION_TYPE_APP);

        $outdatedApps = [];

        foreach ($extensions as $extension) {
            $id = $extension->getLocalId();
            if (!$id) {
                continue;
            }
            $localApp = $this->appRepo->search(new Criteria([$id]), $context)->getEntities()->first();
            if ($localApp === null) {
                continue;
            }

            $nextVersion = $extension->getLatestVersion();
            if (!$nextVersion) {
                continue;
            }

            if (version_compare($nextVersion, $localApp->getVersion()) > 0) {
                $outdatedApps[] = $extension;
            }
        }
        foreach ($outdatedApps as $app) {
            $this->downloader->download($app->getName(), $context);

            try {
                $this->appLifecycle->updateExtension($app->getName(), false, $context);
            } catch (ExtensionUpdateRequiresConsentAffirmationException) {
                // Ignore updates that require consent
            }
        }
    }

    protected function getDecorated(): AbstractAppUpdater
    {
        throw new DecorationPatternException(self::class);
    }
}
