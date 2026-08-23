<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @final
 */
class CachedRuleLoader extends AbstractRuleLoader implements EventSubscriberInterface
{
    final public const string CACHE_KEY = 'rules';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRuleLoader $decorated,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this->decorated;
    }

    public function load(Context $context): RuleCollection
    {
        $key = self::CACHE_KEY . '-' . self::getScopeKey($context);

        return $this->cache->get($key, function (ItemInterface $item) use ($context): RuleCollection {
            $item->tag([self::CACHE_KEY]);

            return $this->decorated->load($context);
        });
    }

    public static function getSubscribedEvents(): array
    {
        return [RuleEvents::RULE_WRITTEN_EVENT => 'invalidate', RuleEvents::RULE_DELETED_EVENT => 'invalidate'];
    }

    public function invalidate(): void
    {
        $this->cache->invalidateTags([self::CACHE_KEY]);
    }

    private static function getScopeKey(Context $context): string
    {
        if ($context->getTenantId() !== null) {
            return 'tenant-' . $context->getTenantId();
        }

        return $context->hasGlobalTenantAccess() ? 'global' : 'platform';
    }
}
