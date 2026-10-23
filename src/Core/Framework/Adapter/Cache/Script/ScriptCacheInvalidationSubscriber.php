<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Script;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ScriptCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ScriptExecutor $scriptExecutor)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'executeCacheInvalidationHook',
        ];
    }

    public function executeCacheInvalidationHook(EntityWrittenContainerEvent $event): void
    {
        $this->scriptExecutor->execute(
            new CacheInvalidationHook($event)
        );
    }
}
