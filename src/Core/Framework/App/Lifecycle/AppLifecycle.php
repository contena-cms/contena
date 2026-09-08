<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
class AppLifecycle extends AbstractAppLifecycle
{
    public function __construct(
        private readonly AppManager $appManager,
        private readonly AppStorage $appStorage,
    ) {
    }

    public function getDecorated(): AbstractAppLifecycle
    {
        throw new DecorationPatternException(self::class);
    }

    public function install(Manifest $manifest, AppInstallParameters $parameters, Context $context): void
    {
        $this->appManager->install($manifest, $parameters, $context);
    }

    public function activate(string $appId, Context $context): void
    {
        $this->appManager->activate($this->loadApp($appId, $context), $context);
    }

    public function deactivate(string $appId, Context $context): void
    {
        $this->appManager->deactivate($this->loadApp($appId, $context), $context);
    }

    public function update(Manifest $manifest, AppUpdateParameters $parameters, array $app, Context $context): void
    {
        $this->appManager->update($manifest, $parameters, $this->loadApp($app['id'], $context), $context);
    }

    public function uninstall(string $appName, array $app, Context $context, bool $keepUserData = false): void
    {
        $this->appManager->uninstall($this->loadApp($app['id'], $context), $context, $keepUserData);
    }

    private function loadApp(string $id, Context $context): AppEntity
    {
        $app = $this->appStorage->findById($id, $context);
        if ($app === null) {
            throw AppException::notFound($id);
        }

        return $app;
    }
}
