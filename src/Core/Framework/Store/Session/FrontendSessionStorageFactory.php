<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Session;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * @internal
 */
class FrontendSessionStorageFactory implements SessionStorageFactoryInterface
{
    /**
     * @param SessionStorageFactoryInterface $decorated The original Symfony factory
     */
    public function __construct(
        private SessionStorageFactoryInterface $decorated,
        private bool $useChannelCookiePath = false,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = $this->decorated->createStorage($request);

        if ($request === null) {
            return $storage;
        }

        if (!$storage instanceof NativeSessionStorage) {
            return $storage;
        }

        if (!$this->useChannelCookiePath) {
            return $storage;
        }

        $storage->setOptions([
            'cookie_path' => $request->attributes->get('ct-channel-base-url') ?: '/',
        ]);

        return $storage;
    }
}
