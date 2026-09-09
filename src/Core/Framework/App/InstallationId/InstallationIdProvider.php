<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-import-type InstallationIdConfig from InstallationId
 */
class InstallationIdProvider implements ResetInterface
{
    final public const INSTALLATION_ID_SYSTEM_CONFIG_KEY = 'core.app.installationId';

    private ?InstallationId $installationId = null;

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection,
        private readonly FingerprintGenerator $fingerprintGenerator
    ) {
    }

    /**
     * @throws InstallationIdChangeSuggestedException
     */
    public function getInstallationId(): InstallationId
    {
        if ($this->installationId) {
            return $this->installationId;
        }

        $this->installationId = $this->fetchInstallationIdFromSystemConfig() ?? $this->regenerateAndSetInstallationId();

        $fingerprintsComparison = $this->fingerprintGenerator->matchFingerprints($this->installationId->fingerprints);
        if (!$fingerprintsComparison->isMatching()) {
            if ($this->hasAppsRegisteredAtAppServers()) {
                throw AppException::installationIdChangeSuggested($this->installationId, $fingerprintsComparison);
            }

            // If the installation does not have any apps, the fingerprints can be updated because no app
            // server knows the installation ID yet.
            $this->regenerateAndSetInstallationId($this->installationId->id);
        }

        return $this->installationId;
    }

    public function regenerateAndSetInstallationId(?string $existingInstallationId = null): InstallationId
    {
        $installationId = InstallationId::create(
            $existingInstallationId ?? Random::getAlphanumericString(16),
            $this->fingerprintGenerator->takeFingerprints(),
        );

        $this->setInstallationId($installationId);

        return $installationId;
    }

    public function deleteInstallationId(): void
    {
        $this->systemConfigService->delete(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY, null, false);

        $this->reset();

        $this->eventDispatcher->dispatch(new InstallationIdDeletedEvent());
    }

    public function reset(): void
    {
        $this->installationId = null;
    }

    private function setInstallationId(InstallationId $installationId): void
    {
        $oldInstallationId = $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($oldInstallationId)) {
            $oldInstallationId = InstallationId::fromSystemConfig($oldInstallationId);
        } else {
            $oldInstallationId = null;
        }

        $this->systemConfigService->set(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY, $installationId->toArray(), null, false);

        // A regeneration can keep the ID while refreshing fingerprints after an APP_URL move.
        // That is not a change of installation identity, so the event only fires when the ID actually differs.
        if ($oldInstallationId?->id !== $installationId->id) {
            $this->eventDispatcher->dispatch(new InstallationIdChangedEvent($installationId, $oldInstallationId));
        }

        $this->installationId = $installationId;
    }

    private function hasAppsRegisteredAtAppServers(): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM app WHERE app_secret IS NOT NULL') > 0;
    }

    private function fetchInstallationIdFromSystemConfig(): ?InstallationId
    {
        /** @var InstallationIdConfig|null $installationId */
        $installationId = $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($installationId)) {
            return InstallationId::fromSystemConfig($installationId);
        }

        return null;
    }
}
