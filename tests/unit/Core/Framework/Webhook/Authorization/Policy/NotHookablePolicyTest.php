<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Framework\Api\Acl\AclRoles;
use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BusinessEventRegistry;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Authorization\Policy\NotHookablePolicy;
use Contena\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\NotHookable;
use Contena\Core\Framework\Webhook\Webhook;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryRequestEvent;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(NotHookablePolicy::class)]
class NotHookablePolicyTest extends TestCase
{
    public function testCoreEventsCarryingTheAttributeAreHandled(): void
    {
        $policy = $this->createPolicy();

        static::assertTrue($policy->handles(AdminUserRecoveryRequestEvent::EVENT_NAME));
        static::assertTrue($policy->handles(UserAccountRecoverRequestEvent::EVENT_NAME));
        static::assertTrue($policy->handles(MailBeforeValidateEvent::EVENT_NAME));
        static::assertTrue($policy->handles(MailBeforeSentEvent::EVENT_NAME));
    }

    public function testEventsWithoutTheAttributeAreNotHandled(): void
    {
        $policy = $this->createPolicy();

        static::assertFalse($policy->handles(UserLoginEvent::EVENT_NAME));
        static::assertFalse($policy->handles(MailSentEvent::EVENT_NAME));
    }

    public function testClassesAddedByABundleAreHandled(): void
    {
        static::assertTrue($this->createPolicy([MarkedEvent::class])->handles('test.marked'));
    }

    public function testAnAppMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy()->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAWebhookWithoutAnAppMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy()->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: null)));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAppSubscriptionIsPermittedBeforeTheNextMajor(): void
    {
        static::assertTrue($this->createPolicy()->permitsSubscription(AdminUserRecoveryRequestEvent::EVENT_NAME, Subscriber::app(static::createStub(Manifest::class))));
    }

    public function testAppSubscriptionIsDeniedFromTheNextMajor(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(AdminUserRecoveryRequestEvent::EVENT_NAME, Subscriber::app(static::createStub(Manifest::class))));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testApiSubscriptionIsDeniedEvenBeforeTheNextMajor(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(AdminUserRecoveryRequestEvent::EVENT_NAME, Subscriber::api(ApiCallerType::AdminUser)));
    }

    /**
     * @param list<class-string<FlowEventAware>> $addedClasses
     */
    private function createPolicy(array $addedClasses = []): NotHookablePolicy
    {
        $registry = new BusinessEventRegistry();
        if ($addedClasses !== []) {
            $registry->addClasses($addedClasses);
        }

        return new NotHookablePolicy($registry);
    }

    private function createWebhook(?string $appName = 'SwagApp'): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: 'admin_user.recovery.request',
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
}

/**
 * @internal
 */
#[NotHookable]
class MarkedEvent extends Event implements FlowEventAware
{
    public function getName(): string
    {
        return 'test.marked';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
