<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Registration;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Manifest\Manifest;
use Psr\Clock\ClockInterface;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
readonly class HandshakeFactory
{
    public function __construct(
        private string $installationUrl,
        private InstallationIdProvider $installationIdProvider,
        private string $contenaVersion,
        private ClockInterface $clock,
    ) {
    }

    public function create(Manifest $manifest, AppEntity $app, #[\SensitiveParameter] ?string $currentSecret = null): AppHandshakeInterface
    {
        $setup = $manifest->getSetup();
        $metadata = $manifest->getMetadata();
        $appName = $metadata->getName();

        if (!$setup) {
            throw AppException::registrationFailed(
                $appName,
                \sprintf('No setup for registration provided in manifest for app "%s".', $metadata->getName())
            );
        }

        $privateSecret = $setup->getSecret();

        try {
            $installationId = $this->installationIdProvider->getInstallationId()->id;
        } catch (InstallationIdChangeSuggestedException $e) {
            throw AppException::registrationFailed(
                $appName,
                $e->getMessage(),
            );
        }

        // The secret the app currently holds, used to sign the re-registration's previous-signature.
        // Normally this is the stored app_secret; recovery passes in the unconfirmed secret the app may
        // already have switched to.
        $currentAppSecret = $currentSecret ?? $app->getAppSecret();

        if ($privateSecret) {
            return new PrivateHandshake(
                $this->installationUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $installationId,
                $this->contenaVersion,
                $this->clock,
                $currentAppSecret,
            );
        }

        throw AppException::registrationFailed(
            $appName,
            'A private app secret is required because no remote app store registration service is configured.',
        );
    }
}
