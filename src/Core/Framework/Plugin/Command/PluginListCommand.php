<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command;

use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Framework\Console\OutputFormatTrait;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Plugin\PluginCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type PluginInfo from KernelPluginLoader
 */
#[AsCommand(
    name: 'plugin:list',
    description: 'Lists all plugins',
)]
class PluginListCommand extends Command
{
    use OutputFormatTrait;

    /**
     * @internal
     *
     * @param EntityRepository<PluginCollection> $pluginRepo
     */
    public function __construct(
        private readonly EntityRepository $pluginRepo,
        private readonly ComposerPluginLoader $composerPluginLoader
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->addFormatOption([self::FORMAT_TABLE, self::FORMAT_JSON]);
        $this->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter the plugin list to a given term');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);
        $context = Context::createCLIContext();

        $format = $this->resolveFormat($input, $output, [self::FORMAT_TABLE, self::FORMAT_JSON]);
        if ($format === null) {
            return self::INVALID;
        }

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $filter = $input->getOption('filter');
        if ($filter) {
            $criteria->addFilter(new MultiFilter(
                MultiFilter::CONNECTION_OR,
                [
                    new ContainsFilter('name', $filter),
                    new ContainsFilter('label', $filter),
                ]
            ));
        }

        $plugins = $this->pluginRepo->search($criteria, $context)->getEntities();

        if ($format === self::FORMAT_JSON) {
            $output->write(json_encode($plugins, \JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $composerInstalled = $this->getComposerPluginLoaderPackages();

        $pluginTable = [];
        $active = $installed = $upgradeable = 0;

        $io->title('Contena Plugin Service');

        if ($filter) {
            $io->comment(\sprintf('Filtering for: %s', $filter));
        }

        $composerInstalledAndRegistered = [];

        foreach ($plugins as $plugin) {
            $pluginActive = $plugin->getActive();
            $pluginInstalled = $plugin->getInstalledAt();
            $pluginUpgradeable = $plugin->getUpgradeVersion();
            $pluginComposerName = $plugin->getComposerName() ?? '';
            $isComposerInstalled = $pluginComposerName !== '' && ($composerInstalled[$pluginComposerName] ?? false);

            $pluginTable[] = [
                $plugin->getName(),
                mb_strimwidth($plugin->getLabel(), 0, 40, '...'),
                $plugin->getComposerName() ?? '',
                $plugin->getVersion(),
                $pluginUpgradeable,
                mb_strimwidth($plugin->getAuthor() ?? '', 0, 40, '...'),
                $pluginInstalled ? 'Yes' : 'No',
                $pluginActive ? 'Yes' : 'No',
                $pluginUpgradeable ? 'Yes' : 'No',
                $isComposerInstalled ? 'Yes' : 'No',
            ];

            if ($isComposerInstalled) {
                $composerInstalledAndRegistered[$pluginComposerName] = true;
            }

            if ($pluginActive) {
                ++$active;
            }

            if ($pluginInstalled) {
                ++$installed;
            }

            if ($pluginUpgradeable) {
                ++$upgradeable;
            }
        }

        foreach ($composerInstalled as $composerName => $plugin) {
            if (isset($composerInstalledAndRegistered[$composerName])) {
                continue;
            }

            $pluginTable[] = [
                $plugin['name'],
                '',
                '',
                $plugin['version'],
                '',
                '',
                'No',
                'No',
                '',
                'Yes',
            ];
        }

        $io->table(
            ['Plugin', 'Label', 'Composer name', 'Version', 'Upgrade version', 'Author', 'Installed', 'Active', 'Upgradeable', 'Required by composer'],
            $pluginTable
        );
        $io->text(
            \sprintf(
                '%d plugins, %d installed, %d active , %d upgradeable',
                \count($pluginTable),
                $installed,
                $active,
                $upgradeable
            )
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, PluginInfo>
     */
    private function getComposerPluginLoaderPackages(): array
    {
        $plugins = $this->composerPluginLoader->fetchPluginInfos();
        $packages = [];
        foreach ($plugins as $plugin) {
            $packages[$plugin['composerName']] = $plugin;
        }

        return $packages;
    }
}
