<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Psr\Cache\CacheItemPoolInterface;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\Util\Random;

class ContentPreviewPayloadStore
{
    private const CACHE_PREFIX = 'content-system.preview.';

    /**
     * @internal
     */
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function store(array $payload): string
    {
        $token = Random::getAlphanumericString(32);
        $item = $this->cache->getItem(self::CACHE_PREFIX . $token);
        $item->set($payload);
        $item->expiresAfter(300);
        if ($this->cache->save($item) === false) {
            throw ContentSystemException::previewPayloadStoreFailed();
        }

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(string $token): ?array
    {
        $item = $this->cache->getItem(self::CACHE_PREFIX . $token);
        $payload = $item->get();

        if (!\is_array($payload)) {
            return null;
        }

        return $payload;
    }
}
