<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTaskHandler;
use Contena\Core\Framework\Webhook\Service\WebhookCleanup;

/**
 * @internal
 */
#[CoversClass(CleanupWebhookEventLogTaskHandler::class)]
class CleanupWebhookEventLogTaskHandlerTest extends TestCase
{
    public function testHandler(): void
    {
        $cleaner = $this->createMock(WebhookCleanup::class);

        $cleaner->expects($this->once())->method('removeOldLogs');

        $handler = new CleanupWebhookEventLogTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cleaner
        );

        $handler->run();
    }
}
