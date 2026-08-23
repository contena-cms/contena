<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
trait AddScaffoldConfigDefaultBehaviour
{
    protected bool $shouldAskCliQuestion = true;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);

            return;
        }

        if ($this->shouldAskCliQuestion && $io->confirm(self::CLI_QUESTION)) {
            $config->addOption(self::OPTION_NAME, true);
        }
    }
}
