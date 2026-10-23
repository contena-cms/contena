<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Authorization\Policy\Policy;
use Contena\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Contena\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriptionRefusals;
use Contena\Core\Framework\Webhook\Authorization\Subscription\SubscriptionValidator;
use Contena\Core\Framework\Webhook\Hookable;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Contena\Core\Framework\Webhook\Webhook;

/**
 * @internal
 */
#[CoversClass(SubscriptionValidator::class)]
class SubscriptionValidatorTest extends TestCase
{
    private SubscriptionValidator $validator;

    protected function setUp(): void
    {
        $collector = static::createStub(HookableEventCollector::class);
        $collector->method('getHookableEventNamesWithPrivileges')->willReturn([
            'blog.written' => ['privileges' => ['blog:read', 'order:read']],
            'order.written' => ['privileges' => []],
        ]);

        $this->validator = new SubscriptionValidator($collector, new PolicyRegistry([new RefuseOrderWrittenPolicy()]));
    }

    public function testEmptySubscriptionsAreSkipped(): void
    {
        $collector = $this->createMock(HookableEventCollector::class);
        $collector->expects($this->never())->method('getHookableEventNamesWithPrivileges');

        $refusals = (new SubscriptionValidator($collector, new PolicyRegistry([])))->validate([], [], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testUnhookableEventIsRefused(): void
    {
        $refusals = $this->validator->validate(['hook' => 'unknown.event'], ['hook' => []], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notHookable: ['hook']), $refusals);
    }

    public function testPolicyRefusalIsReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'order.written'], ['hook' => []], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notPermitted: ['hook']), $refusals);
    }

    public function testMissingPrivilegesAreReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'blog.written'], ['hook' => ['order:read']], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['hook' => ['blog:read']]), $refusals);
    }

    public function testHeldPrivilegesArePermitted(): void
    {
        $refusals = $this->validator->validate(['hook' => 'blog.written'], ['hook' => ['blog:read', 'order:read']], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testEachSubscriptionIsCheckedWithItsOwnPrivileges(): void
    {
        $refusals = $this->validator->validate(
            ['held' => 'blog.written', 'not-held' => 'blog.written'],
            ['held' => ['blog:read', 'order:read'], 'not-held' => ['order:read']],
            Subscriber::api(ApiCallerType::AdminUser),
            Context::createDefaultContext()
        );

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['not-held' => ['blog:read']]), $refusals);
    }

    public function testNullPrivilegesHoldEverything(): void
    {
        $refusals = $this->validator->validate(['hook' => 'blog.written'], ['hook' => null], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testASubscriptionWithoutPrivilegesHoldsNone(): void
    {
        $refusals = $this->validator->validate(['hook' => 'blog.written'], [], Subscriber::api(ApiCallerType::AdminUser), Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['hook' => ['blog:read', 'order:read']]), $refusals);
    }
}

/**
 * @internal
 */
class RefuseOrderWrittenPolicy implements Policy
{
    public function handles(string $eventName): bool
    {
        return $eventName === 'order.written';
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        return false;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        return true;
    }
}
