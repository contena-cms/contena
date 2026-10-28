<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Store;

use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
trait ServiceBehaviour
{
    use ExtensionBehaviour;

    public function installService(string $path, bool $install = true): void
    {
        $appRepository = static::getContainer()->get('app.repository');
        $idResult = $appRepository->searchIds(new Criteria(), Context::createDefaultContext());

        $ids = $idResult->getPrimaryKeyData();
        if (\count($ids)) {
            $appRepository->delete($ids, Context::createDefaultContext());
        }

        $fs = new Filesystem();

        $name = basename($path);
        $appDir = static::getContainer()->getParameter('contena.app_dir') . '/' . $name;
        $fs->mirror($path, $appDir);

        $manifest = Manifest::createFromXmlFile($appDir . '/manifest.xml');
        $manifest->getMetadata()->setSelfManaged(true);

        if ($install) {
            static::getContainer()
                ->get(AppLifecycle::class)
                ->install($manifest, new AppInstallParameters(), Context::createDefaultContext());
        }
    }
}
