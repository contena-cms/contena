<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Event;

use Contena\Core\Framework\App\Event\AppFlowActionEvent;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppFlowActionEvent::class)]
class AppFlowActionEventTest extends TestCase
{
    public function testGetter(): void
    {
        $eventName = 'AppFlowActionEvent';
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'name' => 'value',
        ];

        $event = new AppFlowActionEvent($eventName, $headers, $payload);

        static::assertSame($eventName, $event->getName());
        static::assertSame($headers, $event->getWebhookHeaders());
        static::assertSame($payload, $event->getWebhookPayload());
        static::assertTrue($event->isAllowed('11111', new AclPrivilegeCollection([])));
    }
}
