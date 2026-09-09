<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

/**
 * @internal
 *
 * Fingerprints are put on the installation ID to detect changes in the environment that might suggest a change in the installation ID.
 * They are stored as part of the system configuration and are matched against the runtime stamps any time the installation ID is requested.
 *
 * @see InstallationIdProvider::getInstallationId()
 */
interface Fingerprint
{
    /**
     * A unique identifier for the fingerprint.
     */
    public function getIdentifier(): string;

    /**
     * The score of every mismatching fingerprint is summed up and leads to a suggestion to change the installation ID if it exceeds a certain threshold.
     *
     * A score of 100 indicates a very high certainty that the installation has been permanently moved or cloned to a new environment.
     *
     * @see FingerprintGenerator::STATE_CHANGE_THRESHOLD
     * @see FingerprintGenerator::compare()
     */
    public function getScore(): int;

    /**
     * The runtime stamp of the fingerprint, which is used to compare against the stored stamp.
     */
    public function getStamp(): string;
}
