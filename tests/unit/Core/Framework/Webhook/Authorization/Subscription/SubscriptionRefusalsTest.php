<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;

/**
 * @internal
 */
#[CoversClass(SubscriptionRefusals::class)]
class SubscriptionRefusalsTest extends TestCase
{
    public function testEachMissingPrivilegeIsListedOnce(): void
    {
        $refusals = new SubscriptionRefusals(missingPrivileges: [
            'hook1: order.written' => ['order:read'],
            'hook2: order.deleted' => ['order:read', 'blog:read'],
        ]);

        static::assertSame(['order:read', 'blog:read'], $refusals->allMissingPrivileges);
    }
}
