<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Lifecycle\Registration;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Psr\Clock\ClockInterface;

/**
 * @internal only for use by the app-system
 *
 * @final
 */
readonly class HandshakeFactory
{
    public function __construct(
        private string $shopUrl,
        private ShopIdProvider $shopIdProvider,
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
            $shopId = $this->shopIdProvider->getShopId()->id;
        } catch (ShopIdChangeSuggestedException $e) {
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
                $this->shopUrl,
                $privateSecret,
                $setup->getRegistrationUrl(),
                $metadata->getName(),
                $shopId,
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
