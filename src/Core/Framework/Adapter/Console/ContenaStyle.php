<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Console;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

class ContenaStyle extends SymfonyStyle
{
    public function createProgressBar(int $max = 0): ProgressBar
    {
        $progressBar = parent::createProgressBar($max);

        $character = (string) EnvironmentHelper::getVariable('PROGRESS_BAR_CHARACTER', '');
        if ($character) {
            $progressBar->setProgressCharacter($character);
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
