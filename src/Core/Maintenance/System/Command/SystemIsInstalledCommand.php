<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Util\Database\TableHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command can be used to detect if the system is installed to script a Contena installation or update.
 *
 * @internal
 */
#[AsCommand(
    name: 'system:is-installed',
    description: 'Checks if the system is installed and returns exit code 0 if Contena is installed',
)]
class SystemIsInstalledCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if (TableHelper::tableExists($this->connection, 'migration')) {
                $io->success('Contena is installed');

                return self::SUCCESS;
            }
        } catch (\Throwable) {
        }

        $io->error('Contena is not installed');

        return self::FAILURE;
    }
}
