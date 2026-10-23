<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\AclRoles;
use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Contena\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Webhook;
use Contena\Core\Service\Event\CommercialLicenseProvidedEvent;
use Contena\Core\Service\ServiceWebhookPolicy;

/**
 * @internal
 */
#[CoversClass(ServiceWebhookPolicy::class)]
class ServiceWebhookPolicyTest extends TestCase
{
    public function testItCoversTheLicenseEvent(): void
    {
        static::assertTrue($this->createPolicy()->handles(CommercialLicenseProvidedEvent::NAME));
        static::assertFalse($this->createPolicy()->handles('checkout.user.login'));
    }

    public function testASelfManagedAppMayReceiveIt(): void
    {
        static::assertTrue($this->createPolicy(appIsSelfManaged: true)->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAnAppThatIsNotSelfManagedMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy(appIsSelfManaged: false)->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAnAppThatIsNotActiveMayNotReceiveIt(): void
    {
        $policy = $this->createPolicy(appIsSelfManaged: true);

        static::assertFalse($policy->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: 'OtherApp')));
    }

    public function testAWebhookWithoutAnAppMayNotReceiveIt(): void
    {
        $policy = $this->createPolicy(appIsSelfManaged: true);

        static::assertFalse($policy->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: null)));
    }

    public function testASelfManagedManifestMaySubscribe(): void
    {
        static::assertTrue($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::app($this->createManifest(selfManaged: true))));
    }

    public function testAManifestThatIsNotSelfManagedMayNotSubscribe(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::app($this->createManifest(selfManaged: false))));
    }

    public function testASubscriberWithoutAManifestMayNotSubscribe(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(CommercialLicenseProvidedEvent::NAME, Subscriber::api(ApiCallerType::AdminUser)));
    }

    private function createPolicy(bool $appIsSelfManaged = false): ServiceWebhookPolicy
    {
        $activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $activeAppsLoader->method('getActiveApps')->willReturn([[
            'name' => 'SwagApp',
            'path' => 'custom/apps/SwagApp',
            'author' => null,
            'selfManaged' => $appIsSelfManaged,
        ]]);

        return new ServiceWebhookPolicy($activeAppsLoader);
    }

    private function createWebhook(?string $appName = 'SwagApp'): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: CommercialLicenseProvidedEvent::NAME,
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: $appName === null ? null : 'app-id',
            appName: $appName,
            appSourceType: null,
            appActive: true,
            appVersion: null,
            appSecret: null,
            roles: new AclRoles(false, []),
        );
    }

    private function createManifest(bool $selfManaged): Manifest
    {
        $metadata = static::createStub(Metadata::class);
        $metadata->method('isSelfManaged')->willReturn($selfManaged);

        $manifest = static::createStub(Manifest::class);
        $manifest->method('getMetadata')->willReturn($metadata);

        return $manifest;
    }
}
