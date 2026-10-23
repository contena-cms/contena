<?php declare(strict_types=1);

namespace Contena\Core\Service\Command;

use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\App\Command\UninstallAppCommand;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\Context;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\ServiceLifecycle;
use Contena\Core\Service\ServiceStorage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
class UninstallAppCommandDecorator extends UninstallAppCommand
{
    public function __construct(
        AbstractAppLifecycle $appLifecycle,
        AppStorage $appStorage,
        private readonly ServiceStorage $serviceStorage,
        private readonly ServiceLifecycle $serviceLifecycle,
        private readonly LifecycleManager $lifecycleManager
    ) {
        parent::__construct($appLifecycle, $appStorage);
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the app')]
        string $name,
        #[Option(description: 'Keep admin user data of the app')]
        bool $keepUserData = false,
        #[Option(description: 'Use this option to skip recompiling of all themes')]
        bool $skipThemeCompile = false,
    ): int {
        if ($this->lifecycleManager->enabled()) {
            return parent::__invoke($io, $name, $keepUserData, $skipThemeCompile);
        }

        $context = Context::createCLIContext();

        $service = $this->serviceStorage->findByName($name, $context);

        if ($service === null) {
            return parent::__invoke($io, $name, $keepUserData, $skipThemeCompile);
        }

        $this->serviceLifecycle->uninstall($service->name, $context);

        $io->success('App uninstalled successfully.');

        return self::SUCCESS;
    }
}
