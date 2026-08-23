<?php declare(strict_types=1);

namespace Contena\Core\Framework\Feature\Command;

use Contena\Core\Framework\Feature;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'feature:list', description: 'List all registered features')]
final class FeatureListCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $features = Feature::getRegisteredFeatures();

        $formatted = [];

        if ($features === []) {
            $io->info('No features are registered.');

            return self::SUCCESS;
        }

        foreach ($features as $code => $feature) {
            $formatted[] = [
                $code,
                $feature['name'] ?? $code,
                $feature['description'] ?? '',
                Feature::isActive($code) ? '<info>Enabled</>' : '<error>Disabled</>',
            ];
        }

        $io->info('All features that are registered:');

        $io->table(['Code', 'Name', 'Description', 'Status'], $formatted);

        return self::SUCCESS;
    }
}
