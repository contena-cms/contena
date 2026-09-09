<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Url;

use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;

/**
 * @internal
 */
class AppUrlVerificationPrinter
{
    public function __construct(
        private readonly InstallationIdProvider $installationIdProvider,
    ) {
    }

    public function print(SymfonyStyle $io, VerificationState $state, bool $manual = false): void
    {
        $io->title('App URL Verification Status');

        $installationId = $this->installationIdProvider->getInstallationId();
        $io->writeln(\sprintf("<info>APP URL: %s</info>\n", $installationId->getFingerprint(AppUrl::IDENTIFIER)));

        $io->definitionList(
            ['Result' => match ($state->status) {
                VerificationStatus::PASS => '<info>OK</info>',
                VerificationStatus::SOFT_FAIL => '<comment>SOFT FAIL</comment> - please try again',
                VerificationStatus::HARD_FAIL => '<error>HARD FAIL</error> - APP_URL is incorrect or not reachable',
            }],
            ['Info' => $this->infoSummary($state)],
            ['Tries' => $manual ? 'Manual attempt' : (string) $state->numTries],
            ['Checked at' => $state->at->format('Y-m-d H:i:s T')]
        );

        $io->note('When a hard fail occurs, app communication will be disabled. A soft fail usually means a temporary problem, and Contena will retry automatically with longer pauses, up to once per hour.');

        // @todo: remove when we enable comms kill switch
        $io->note('App communication will only be disabled in a future release.');
    }

    private function infoSummary(VerificationState $state): string
    {
        if ($state->info) {
            $terminal = new Terminal();

            $info = $state->info;

            $width = $terminal->getWidth();
            $maxLength = max(0, $width - 25);

            if ($maxLength > 0 && \strlen($info) > $maxLength) {
                return \substr($info, 0, $maxLength) . ' ...';
            }

            return $info;
        }

        return 'No additional information available';
    }
}
