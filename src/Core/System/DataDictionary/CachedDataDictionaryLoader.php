<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Hasher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @internal
 */
class CachedDataDictionaryLoader implements DataDictionaryLoaderInterface, EventSubscriberInterface
{
    final public const string CACHE_TAG = 'data-dictionary';

    public function __construct(
        private readonly DataDictionaryLoaderInterface $decorated,
        private readonly TagAwareCacheInterface $cache
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataDictionaryEvents::DATA_DICTIONARY_WRITTEN_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_DELETED_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_TRANSLATION_WRITTEN_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_TRANSLATION_DELETED_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_WRITTEN_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_DELETED_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_TRANSLATION_WRITTEN_EVENT => 'invalidateCache',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_TRANSLATION_DELETED_EVENT => 'invalidateCache',
        ];
    }

    public function load(string $technicalName, Context $context): ?DataDictionaryEntity
    {
        $key = 'data-dictionary-' . Hasher::hash(implode('|', [
            $technicalName,
            $context->getVersionId(),
            ...$context->getLanguageIdChain(),
        ]));

        $value = $this->cache->get($key, function (ItemInterface $item) use ($technicalName, $context): string {
            $item->tag([self::CACHE_TAG]);

            return CacheValueCompressor::compress($this->decorated->load($technicalName, $context));
        });

        return CacheValueCompressor::uncompress($value);
    }

    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }
}
