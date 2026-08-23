<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Command;

use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Console\ContenaStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'cache:clear:http', description: 'Clear only the HTTP cache')]
class CacheClearHttpCommand extends Command
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheClearer $cacheClearer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ContenaStyle($input, $output);

        $io->comment('Clearing the HTTP cache');

        $this->cacheClearer->clearHttpCache();

        return self::SUCCESS;
    }
}
