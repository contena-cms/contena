<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Contena\Core\Framework\Webhook\Authorization\WebhookSubscription;

/**
 * @internal
 */
#[CoversClass(WebhookSubscription::class)]
class WebhookSubscriptionTest extends TestCase
{
    public function testOnlyAnAppsWebhookBelongsToAnApp(): void
    {
        static::assertTrue((new WebhookSubscription(Uuid::randomHex(), SubscriberType::App, Uuid::randomHex(), 'blog.written', null))->belongsToApp());
        static::assertFalse((new WebhookSubscription(Uuid::randomHex(), SubscriberType::AdminUser, Uuid::randomHex(), 'blog.written', null))->belongsToApp());
    }

    public function testAUsersWebhookIsOwnedOnlyByThatUser(): void
    {
        $admin_userId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::AdminUser, $admin_userId, 'blog.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource($admin_userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $admin_userId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($admin_userId, Uuid::randomHex())));
    }

    public function testAnIntegrationsWebhookIsOwnedOnlyByThatIntegration(): void
    {
        $integrationId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::Integration, $integrationId, 'blog.written', null);

        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(null, $integrationId)));
        static::assertTrue($subscription->isOwnedBy(new AdminApiSource(Uuid::randomHex(), $integrationId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, Uuid::randomHex())));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($integrationId)));
    }

    public function testAnAppsWebhookIsOwnedByNoApiCaller(): void
    {
        $appId = Uuid::randomHex();
        $subscription = new WebhookSubscription(Uuid::randomHex(), SubscriberType::App, $appId, 'blog.written', null);

        static::assertFalse($subscription->isOwnedBy(new AdminApiSource($appId)));
        static::assertFalse($subscription->isOwnedBy(new AdminApiSource(null, $appId)));
    }
}
