<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:url:verify',
    description: 'Check the status of the app URL and force verification',
)]
class AppUrlVerifyCommand extends Command
{

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $shopId = $this->shopIdProvider->getShopId();
        $this->appUrlVerifier->forceVerify($shopId, true);

        $state = $this->appUrlVerifier->getCurrentState();

        if ($state === null) {
            $io->warning('No verification state found. Check your cache configuration.');

            return Command::SUCCESS;
        }

        $this->printer->print($io, $state, true);

        return Command::SUCCESS;
    }
}
