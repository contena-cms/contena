<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Subscriber;

use Contena\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final readonly class UpdatePostFinishSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private TaskRegistry $registry)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [UpdatePostFinishEvent::class => 'updatePostFinishEvent'];
    }

    public function updatePostFinishEvent(): void
    {
        $this->registry->registerTasks();
    }
}
