<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\System\Command;

use Psr\Clock\ClockInterface;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Contena\Core\Installer\Finish\SystemLocker;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Contena\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Contena\Core\Maintenance\System\Struct\DatabaseConnectionInformation;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal should be used over the CLI only
 */
#[AsCommand(
    name: 'system:install',
    description: 'Installs the Contena system',
)]
class SystemInstallCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly SetupDatabaseAdapter $setupDatabaseAdapter,
        private readonly DatabaseConnectionFactory $databaseConnectionFactory,
        private readonly CacheClearer $cacheClearer,
        private readonly SystemLocker $systemLocker,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('create-database', null, InputOption::VALUE_NONE, 'Create database if it doesn\'t exist.')
            ->addOption('drop-database', null, InputOption::VALUE_NONE, 'Drop existing database')
            ->addOption('basic-setup', null, InputOption::VALUE_NONE, 'Create the initial administrator')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force install even if install.lock exists')
            ->addOption('no-assign-theme', null, InputOption::VALUE_NONE, 'Do not assign the default theme')
            ->addOption('skip-assets-install', null, InputOption::VALUE_NONE, 'Skips installing of assets')
            ->addOption('skip-first-run-wizard', null, InputOption::VALUE_NONE, 'Skips the first run wizard')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new ContenaStyle($input, $output);

        // set default
        $isBlueGreen = EnvironmentHelper::getVariable('BLUE_GREEN_DEPLOYMENT', '1');
        $_SERVER['BLUE_GREEN_DEPLOYMENT'] = $isBlueGreen;
        $_ENV['BLUE_GREEN_DEPLOYMENT'] = $isBlueGreen;
        putenv('BLUE_GREEN_DEPLOYMENT=' . $isBlueGreen);

        if (!$input->getOption('force') && \is_file($this->projectDir . '/install.lock')) {
            $output->comment('install.lock already exists. Delete it or pass --force to do it anyway.');

            return self::FAILURE;
        }

        // Delete old object cache, which can lead to wrong assumptions
        $this->cacheClearer->clearObjectCache();

        $this->initializeDatabase($output, $input);

        $commands = [
            [
                'command' => 'database:migrate',
                'identifier' => 'core',
                '--all' => true,
            ],
            [
                'command' => 'database:migrate-destructive',
                'identifier' => 'core',
                '--all' => true,
                '--version-selection-mode' => 'all',
            ],
            [
                'command' => 'dal:refresh:index',
            ],
            [
                'command' => 'scheduled-task:register',
            ],
            [
                'command' => 'plugin:refresh',
            ],
        ];

        $application = $this->getConsoleApplication();
        if ($application->has('theme:refresh')) {
            $commands[] = [
                'command' => 'theme:refresh',
            ];
        }

        if ($application->has('theme:compile')) {
            $commands[] = [
                'command' => 'theme:compile',
                '--sync' => true,
                'allowedToFail' => true,
            ];
        }

        if ($input->getOption('basic-setup')) {
            $commands[] = [
                'command' => 'user:create',
                'username' => 'supperadmin',
                '--admin' => true,
                '--password' => 'contenaAdmin',
            ];
            $commands[] = [
                'command' => 'user:create',
                'username' => UserProvisioner::DEFAULT_ADMIN_USERNAME,
                '--password' => 'contenaAdmin',
                '--name' => UserProvisioner::DEFAULT_ADMIN_USERNAME,
                '--email' => UserProvisioner::DEFAULT_ADMIN_EMAIL,
                '--role' => UserProvisioner::ADMINISTRATOR_ROLE_CODE,
            ];

            if ($application->has('theme:change') && !$input->getOption('no-assign-theme')) {
                $commands[] = [
                    'command' => 'theme:change',
                    'allowedToFail' => true,
                    '--all' => true,
                    '--sync' => true,
                    'theme-name' => 'Frontend',
                ];
            }
        }

        if (!$input->getOption('skip-assets-install')) {
            $commands[] = [
                'command' => 'assets:install',
            ];
        }

        $commands[] = [
            'command' => 'cache:clear',
        ];

        if ($input->getOption('skip-first-run-wizard')) {
            $commands[] = [
                'command' => 'system:config:set',
                'key' => 'core.frw.completedAt',
                'value' => $this->clock->now()->format('Y-m-d H:i:s'),
            ];
        }

        $result = $this->runCommands($commands, $output);

        if ($result !== self::SUCCESS) {
            return $result;
        }

        if ($this->shouldSkipFileOperations()) {
            $output->comment('Skipping install.lock and .htaccess creation (CONTENA_SKIP_WEBINSTALLER is set)');

            return $result;
        }

        $this->ensureHtaccessExists();
        $this->systemLocker->lock();

        return $result;
    }

    /**
     * @param array<int, array<string, string|bool|null>> $commands
     */
    private function runCommands(array $commands, OutputInterface $output): int
    {
        foreach ($commands as $parameters) {
            // remove params with null value
            $parameters = array_filter($parameters);

            $output->writeln('');

            $allowedToFail = $parameters['allowedToFail'] ?? false;
            unset($parameters['allowedToFail']);

            try {
                $returnCode = $this->getConsoleApplication()->doRun(new ArrayInput($parameters), $output);

                if ($returnCode !== 0 && !$allowedToFail) {
                    return $returnCode;
                }
            } catch (\Throwable $e) {
                if (!$allowedToFail) {
                    throw $e;
                }
            }
        }

        return self::SUCCESS;
    }

    private function initializeDatabase(ContenaStyle $output, InputInterface $input): void
    {
        $databaseConnectionInformation = DatabaseConnectionInformation::fromEnv();

        $connection = $this->databaseConnectionFactory->getConnection($databaseConnectionInformation, true);

        $output->writeln('Prepare installation');
        $output->writeln('');

        $dropDatabase = $input->getOption('drop-database');
        if ($dropDatabase) {
            $this->setupDatabaseAdapter->dropDatabase($connection, $databaseConnectionInformation->getDatabaseName());
            $output->writeln('Drop database `' . $databaseConnectionInformation->getDatabaseName() . '`');
        }

        if ($input->getOption('create-database') || $dropDatabase) {
            $this->setupDatabaseAdapter->createDatabase($connection, $databaseConnectionInformation->getDatabaseName());
            $output->writeln('Created database `' . $databaseConnectionInformation->getDatabaseName() . '`');
        }

        $importedBaseSchema = $this->setupDatabaseAdapter->initializeContenaDb($connection, $databaseConnectionInformation->getDatabaseName());

        if ($importedBaseSchema) {
            $output->writeln('Imported base schema.sql');
        }

        $output->writeln('');
    }

    private function getConsoleApplication(): Application
    {
        $application = $this->getApplication();
        if (!$application instanceof Application) {
            throw MaintenanceException::consoleApplicationNotFound();
        }

        return $application;
    }

    private function shouldSkipFileOperations(): bool
    {
        return (bool) EnvironmentHelper::getVariable('CONTENA_SKIP_WEBINSTALLER', false);
    }

    private function ensureHtaccessExists(): void
    {
        $htaccessPath = $this->projectDir . '/public/.htaccess';
        $htaccessDistPath = $this->projectDir . '/public/.htaccess.dist';

        if (\is_file($htaccessPath)) {
            return;
        }

        if (!\is_file($htaccessDistPath)) {
            return;
        }

        copy($htaccessDistPath, $htaccessPath);
    }
}
