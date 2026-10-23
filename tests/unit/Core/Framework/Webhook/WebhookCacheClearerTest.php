<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEvents;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Service\WebhookManager;
use Contena\Core\Framework\Webhook\WebhookCacheClearer;

/**
 * @internal
 */
#[CoversClass(WebhookCacheClearer::class)]
class WebhookCacheClearerTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            AppEvents::APP_WRITTEN_EVENT => 'clearWebhookCache',
        ], WebhookCacheClearer::getSubscribedEvents());
    }

    public function testReset(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects($this->once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->reset();
    }

    public function testClearWebhookCache(): void
    {
        $manager = $this->createMock(WebhookManager::class);
        $manager->expects($this->once())
            ->method('clearInternalWebhookCache');

        $cacheClearer = new WebhookCacheClearer($manager);
        $cacheClearer->clearWebhookCache();
    }
}
