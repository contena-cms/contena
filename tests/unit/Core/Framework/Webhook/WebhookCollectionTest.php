<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\WebhookCollection;
use Contena\Core\Framework\Webhook\WebhookEntity;

/**
 * @internal
 */
#[CoversClass(WebhookCollection::class)]
class WebhookCollectionTest extends TestCase
{
    public function testFilterForEvent(): void
    {
        $match = self::webhook('order', 'checkout.order.placed');

        $collection = new WebhookCollection([$match, self::webhook('user', 'user.registered')]);
        $filtered = $collection->filterForEvent('checkout.order.placed');

        static::assertSame([$match], array_values($filtered->getElements()));
    }

    public function testGetAclRoleIdsAsBinaryCollectsOnlyAppWebhooks(): void
    {
        $aclRoleId = Uuid::randomHex();

        $appWebhook = self::webhook('app-hook', 'checkout.order.placed');
        $app = new AppEntity();
        $app->setAclRoleId($aclRoleId);
        $appWebhook->setApp($app);

        $collection = new WebhookCollection([$appWebhook, self::webhook('plain', 'user.registered')]);

        static::assertSame([Uuid::fromHexToBytes($aclRoleId)], $collection->getAclRoleIdsAsBinary());
    }

    public function testAllowedForDispatching(): void
    {
        $plain = self::webhook('plain', 'user.registered');

        $activeApp = new AppEntity();
        $activeApp->setActive(true);
        $activeAppWebhook = self::webhook('active-app', 'checkout.order.placed');
        $activeAppWebhook->setApp($activeApp);

        $inactiveApp = new AppEntity();
        $inactiveApp->setActive(false);
        $inactiveAppWebhook = self::webhook('inactive-app', 'checkout.order.placed');
        $inactiveAppWebhook->setApp($inactiveApp);

        $lifecycleWebhook = self::webhook('lifecycle', AppUpdatedEvent::NAME);
        $inactiveAppForLifecycle = new AppEntity();
        $inactiveAppForLifecycle->setActive(false);
        $lifecycleWebhook->setApp($inactiveAppForLifecycle);

        $collection = new WebhookCollection([$plain, $activeAppWebhook, $inactiveAppWebhook, $lifecycleWebhook]);

        static::assertSame(
            ['plain', 'active-app', 'lifecycle'],
            array_keys($collection->allowedForDispatching()->getElements())
        );
    }

    private static function webhook(string $id, string $eventName): WebhookEntity
    {
        $webhook = new WebhookEntity();
        $webhook->setUniqueIdentifier($id);
        $webhook->setEventName($eventName);

        return $webhook;
    }
}
