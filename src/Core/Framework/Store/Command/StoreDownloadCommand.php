<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[AsCommand(
    name: 'store:download',
    description: 'Downloads a plugin from the store',
)]
class StoreDownloadCommand extends Command
{
    /**
     * @param EntityRepository<PluginCollection> $pluginRepo
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     */
    public function __construct(
        private readonly StoreClient $storeClient,
        private readonly EntityRepository $pluginRepo,
        private readonly PluginManagementService $pluginManagementService,
        private readonly PluginLifecycleService $pluginLifecycleService,
        private readonly EntityRepository $admin_userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pluginName', 'p', InputOption::VALUE_REQUIRED, 'Name of plugin')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language')
            ->addOption('admin_user', 'u', InputOption::VALUE_OPTIONAL, 'AdminUser')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createCLIContext();

        $pluginName = (string) $input->getOption('pluginName');
        $admin_user = $input->getOption('admin_user');

        $context = $this->getUserContextFromInput($admin_user, $context);

        $this->validatePluginIsNotManagedByComposer($pluginName, $context);

        try {
            $data = $this->storeClient->getDownloadDataForPlugin($pluginName, $context);
        } catch (ClientException $exception) {
            throw StoreException::storeError($exception);
        }

        $this->pluginManagementService->downloadStorePlugin($data, $context);

        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            // don't update plugins that are not installed
            return self::SUCCESS;
        }

        if ($plugin->getUpgradeVersion()) {
            $this->pluginLifecycleService->updatePlugin($plugin, $context);
        }

        return self::SUCCESS;
    }

    private function getUserContextFromInput(?string $admin_userName, Context $context): Context
    {
        if (!$admin_userName) {
            return $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('admin_user.username', $admin_userName));

        $admin_userEntity = $this->admin_userRepository->search($criteria, $context)->getEntities()->first();
        if ($admin_userEntity === null) {
            return $context;
        }

        return Context::createCLIContext(new AdminApiSource($admin_userEntity->getId()));
    }

    private function validatePluginIsNotManagedByComposer(string $pluginName, Context $context): void
    {
        $plugin = $this->getPluginFromInput($pluginName, $context);

        if ($plugin === null) {
            return;
        }

        if ($plugin->getManagedByComposer() && !$plugin->isLocatedInCustomPluginDirectory()) {
            throw StoreException::cannotDeleteManaged($pluginName);
        }
    }

    private function getPluginFromInput(string $pluginName, Context $context): ?PluginEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('plugin.name', $pluginName));

        return $this->pluginRepo->search($criteria, $context)->getEntities()->first();
    }
}
