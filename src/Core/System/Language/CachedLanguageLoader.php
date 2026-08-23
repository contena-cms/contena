<?php declare(strict_types=1);

namespace Contena\Core\System\Language;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @phpstan-import-type LanguageData from LanguageLoaderInterface
 */
class CachedLanguageLoader implements LanguageLoaderInterface, EventSubscriberInterface
{
    private const string CACHE_KEY = 'contena.languages';

    /**
     * @internal
     */
    public function __construct(
        private readonly LanguageLoaderInterface $loader,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LanguageEvents::LANGUAGE_DELETED_EVENT => 'invalidateCache',
            LanguageEvents::LANGUAGE_WRITTEN_EVENT => 'invalidateCache',
        ];
    }

    /**
     * @return LanguageData
     */
    public function loadLanguages(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->loader->loadLanguages());
    }

    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
