<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Command;

use Contena\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Contena\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:shop-id:change',
    description: 'Change the shop ID by choosing a resolution strategy',
)]
class ChangeShopIdCommand extends Command
{
    public function __construct(private readonly Resolver $shopIdChangeResolver)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('strategy', InputArgument::OPTIONAL, 'The strategy that should be applied');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $availableStrategies = $this->shopIdChangeResolver->getAvailableStrategies();
        $strategy = $input->getArgument('strategy');

        if ($strategy === null || !\array_key_exists($strategy, $availableStrategies)) {
            if ($strategy !== null) {
                $io->note(\sprintf('Strategy with name: "%s" not found.', $strategy));
            }

            $strategy = $io->choice(
                'Choose what strategy should be applied when changing the shop ID?',
                $availableStrategies
            );
        }

        $this->shopIdChangeResolver->resolve($strategy, Context::createCLIContext());

        $io->success('Strategy "' . $strategy . '" was applied successfully');

        return self::SUCCESS;
    }
}
