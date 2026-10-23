<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;

/**
 * @internal only for use by the app-system
 */
class AppService
{
    public function __construct(
        private readonly AppLifecycleIterator $appLifecycleIterator,
        private readonly AbstractAppLifecycle $appLifecycle
    ) {
    }

    /**
     * @param array<string> $installAppNames - Apps that should be installed
     *
     * @return list<array{manifest: Manifest, exception: \Exception}>
     */
    public function doRefreshApps(
        AppInstallParameters $parameters,
        Context $context,
        array $installAppNames = []
    ): array {
        return $this->appLifecycleIterator->iterateOverApps(
            $this->appLifecycle,
            $parameters,
            $context,
            $installAppNames
        );
    }

    public function getRefreshableAppInfo(Context $context): RefreshableAppDryRun
    {
        $appInfo = new RefreshableAppDryRun();

        $this->appLifecycleIterator->iterateOverApps(
            $appInfo,
            new AppInstallParameters(
                activate: false,
                acceptPermissions: false
            ),
            $context
        );

        return $appInfo;
    }
}
