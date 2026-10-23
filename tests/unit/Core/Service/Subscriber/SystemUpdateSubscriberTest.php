<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\Subscriber\SystemUpdateSubscriber;

/**
 * @internal
 */
#[CoversClass(SystemUpdateSubscriber::class)]
class SystemUpdateSubscriberTest extends TestCase
{
    public function testSyncDelegatesToLifecycleManager(): void
    {
        $context = new Context(new SystemSource());
        $lifecycleManager = $this->createMock(LifecycleManager::class);
        $lifecycleManager->expects($this->once())
            ->method('sync')
            ->with($context);

        $subscriber = new SystemUpdateSubscriber($lifecycleManager, static::createStub(LoggerInterface::class));
        $subscriber->sync(new UpdatePostFinishEvent($context, '6.7.0.0', '6.7.1.0'));
    }
}
