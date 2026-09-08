<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Subscriber;

use Contena\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\ShopId\ShopIdChangedEvent;
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

    public function __invoke(ShopIdChangedEvent $event): void
    {
        $newUrl = $event->newShopId->getFingerprint(AppUrl::IDENTIFIER);
        $oldUrl = $event->oldShopId?->getFingerprint(AppUrl::IDENTIFIER);

        if ($newUrl && $newUrl !== $oldUrl) {
            $this->appUrlVerifier->forceVerify($event->newShopId);
        }
    }
}
