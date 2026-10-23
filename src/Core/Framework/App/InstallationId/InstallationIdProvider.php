<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationId;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * @phpstan-import-type InstallationIdV1Config from InstallationId
 * @phpstan-import-type InstallationIdV2Config from InstallationId
 */
class InstallationIdProvider implements ResetInterface
{
    final public const INSTALLATION_ID_SYSTEM_CONFIG_KEY = 'core.app.installationId';
    final public const INSTALLATION_ID_SYSTEM_CONFIG_KEY_V2 = 'core.app.installationIdV2';

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

            // if the shop does not have any apps we can update the existing shop id value
            // with the new APP_URL as no app knows the shop id
            $this->regenerateAndSetInstallationId($this->installationId->id);
        }

        return $this->installationId;
    }

    public function regenerateAndSetInstallationId(?string $existingInstallationId = null): InstallationId
    {
        $installationId = InstallationId::v2(
            $existingInstallationId ?? Random::getAlphanumericString(16),
            $this->fingerprintGenerator->takeFingerprints(),
        );

        $this->setInstallationId($installationId);

        return $installationId;
    }

    public function deleteInstallationId(): void
    {
        $this->systemConfigService->delete(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY, null, true);
        $this->systemConfigService->delete(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY_V2, null, false);

        $this->reset();

        $this->eventDispatcher->dispatch(new InstallationIdDeletedEvent());
    }

    public function reset(): void
    {
        $this->installationId = null;
    }

    private function setInstallationId(InstallationId $installationId): void
    {
        $oldInstallationId = $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY_V2)
            ?? $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($oldInstallationId)) {
            $oldInstallationId = InstallationId::fromSystemConfig($oldInstallationId);
        } else {
            $oldInstallationId = null;
        }

        $this->systemConfigService->set(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY_V2, $installationId->toArray(), null, false);

        // A regeneration can keep the id while refreshing fingerprints (APP_URL move, V1->V2 upgrade);
        // that is not a change of shop identity, so the event only fires when the id actually differs.
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
        /** @var InstallationIdV2Config|null $installationIdV2 */
        $installationIdV2 = $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY_V2);
        if (\is_array($installationIdV2)) {
            return InstallationId::fromSystemConfig($installationIdV2);
        }

        /** @var InstallationIdV1Config|null $installationIdV1 */
        $installationIdV1 = $this->systemConfigService->get(self::INSTALLATION_ID_SYSTEM_CONFIG_KEY);
        if (\is_array($installationIdV1)) {
            $installationIdV1 = InstallationId::fromSystemConfig($installationIdV1);

            return $this->regenerateAndSetInstallationId($installationIdV1->id);
        }

        return null;
    }
}
