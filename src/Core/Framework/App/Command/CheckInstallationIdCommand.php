<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Command;

use Contena\Core\Framework\App\InstallationId\FingerprintComparisonResult;
use Contena\Core\Framework\App\InstallationId\FingerprintGenerator;
use Contena\Core\Framework\App\InstallationId\InstallationId;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(
    name: 'app:installation-id:check',
    description: 'Check if an installation ID change is suggested',
)]
class CheckInstallationIdCommand extends Command
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly FingerprintGenerator $fingerprintGenerator,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $installationIdConfig = $this->systemConfigService->get(InstallationIdProvider::INSTALLATION_ID_SYSTEM_CONFIG_KEY);

        if (!\is_array($installationIdConfig)) {
            $io->success('No installation ID has been generated yet.');

            return self::SUCCESS;
        }

        $installationId = InstallationId::fromSystemConfig($installationIdConfig);
        $result = $this->fingerprintGenerator->matchFingerprints($installationId->fingerprints);

        $this->renderInstallationIdTable($io, $installationId);
        $this->renderFingerprintsTable($io, $result);
        $this->renderResult($io, $result);

        return $result->isMatching() ? self::SUCCESS : self::FAILURE;
    }

    private function renderInstallationIdTable(SymfonyStyle $io, InstallationId $installationId): void
    {
        $installationIdTable = new Table($io);
        $installationIdTable->setVertical();
        $installationIdTable->setHeaders(['Installation ID', 'Version']);
        $installationIdTable->addRow([$installationId->id, $installationId->version]);
        $installationIdTable->render();

        $io->writeln('');
    }

    private function renderFingerprintsTable(SymfonyStyle $io, FingerprintComparisonResult $result): void
    {
        $fingerprintsTable = new Table($io);
        $fingerprintsTable->setHeaders(['Fingerprint', 'Old Value', 'New Value', 'Score', 'State']);

        foreach ($result->mismatchingFingerprints as $fingerprint) {
            $fingerprintsTable->addRow([$fingerprint->identifier, $fingerprint->storedStamp, $fingerprint->expectedStamp, $fingerprint->score, '<fg=red>✘ MISMATCH</>']);
        }

        foreach ($result->matchingFingerprints as $fingerprint) {
            $fingerprintsTable->addRow([$fingerprint->identifier, $fingerprint->storedStamp, $fingerprint->storedStamp, $fingerprint->score, '<fg=green>✔ MATCH</>']);
        }

        $fingerprintsTable->render();

        $io->writeln('');
    }

    private function renderResult(SymfonyStyle $io, FingerprintComparisonResult $result): void
    {
        if ($result->isMatching()) {
            $io->success('Installation ID change not suggested.');
        } else {
            $io->warning(\sprintf('Installation ID change suggested (Score: %s/%s). Run "bin/console app:installation-id:change" to change the installation ID.', $result->score, $result->threshold));
        }
    }
}
