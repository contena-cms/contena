<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Subscriber;

use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\InstallationId\InstallationIdChangedEvent;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 */
#[AsEventListener]
class VerifyAppUrlListener
{
    public function __construct(private readonly AppUrlVerifier $appUrlVerifier)
    {
    }

    public function __invoke(InstallationIdChangedEvent $event): void
    {
        $newUrl = $event->newInstallationId->getFingerprint(AppUrl::IDENTIFIER);
        $oldUrl = $event->oldInstallationId?->getFingerprint(AppUrl::IDENTIFIER);

        if ($newUrl && $newUrl !== $oldUrl) {
            $this->appUrlVerifier->forceVerify($event->newInstallationId);
        }
    }
}
