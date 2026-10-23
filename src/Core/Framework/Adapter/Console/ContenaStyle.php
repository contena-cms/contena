<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Console;

use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Feature;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @deprecated tag:v6.8.0 - Will be removed. Use {@see SymfonyStyle} instead
 */
class ContenaStyle extends SymfonyStyle
{
    public function createProgressBar(int $max = 0): ProgressBar
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
        $progressBar = parent::createProgressBar($max);

        $character = (string) EnvironmentHelper::getVariable('PROGRESS_BAR_CHARACTER', '');
        if ($character) {
            $progressBar->setProgressCharacter($character);
        }

        $progressBar->setBarCharacter('<fg=magenta>=</>');

        return $progressBar;
    }
}
