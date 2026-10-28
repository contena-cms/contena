<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Webhook\Authorization\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\ApiCaller;
use Contena\Core\Framework\Api\Context\AdminApiSource;
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
#[Package('framework')]
#[CoversClass(SubscriptionValidator::class)]
class SubscriptionValidatorTest extends TestCase
{
    private SubscriptionValidator $validator;

    private Subscriber $subscriber;

    protected function setUp(): void
    {
        $caller = ApiCaller::of(new AdminApiSource('user-id'));
        static::assertNotNull($caller);
        $this->subscriber = Subscriber::api($caller);

        $collector = static::createStub(HookableEventCollector::class);
        $collector->method('getHookableEventNamesWithPrivileges')->willReturn([
            'product.written' => ['privileges' => ['product:read', 'order:read']],
            'order.written' => ['privileges' => []],
        ]);

        $this->validator = new SubscriptionValidator($collector, new PolicyRegistry([new RefuseOrderWrittenPolicy()]));
    }

    public function testEmptySubscriptionsAreSkipped(): void
    {
        $collector = $this->createMock(HookableEventCollector::class);
        $collector->expects($this->never())->method('getHookableEventNamesWithPrivileges');

        $refusals = (new SubscriptionValidator($collector, new PolicyRegistry([])))->validate([], [], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testUnhookableEventIsRefused(): void
    {
        $refusals = $this->validator->validate(['hook' => 'unknown.event'], ['hook' => []], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notHookable: ['hook']), $refusals);
    }

    public function testPolicyRefusalIsReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'order.written'], ['hook' => []], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(notPermitted: ['hook']), $refusals);
    }

    public function testMissingPrivilegesAreReported(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], ['hook' => ['order:read']], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['hook' => ['product:read']]), $refusals);
    }

    public function testHeldPrivilegesArePermitted(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], ['hook' => ['product:read', 'order:read']], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testEachSubscriptionIsCheckedWithItsOwnPrivileges(): void
    {
        $refusals = $this->validator->validate(
            ['held' => 'product.written', 'not-held' => 'product.written'],
            ['held' => ['product:read', 'order:read'], 'not-held' => ['order:read']],
            $this->subscriber,
            Context::createDefaultContext()
        );

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['not-held' => ['product:read']]), $refusals);
    }

    public function testNullPrivilegesHoldEverything(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], ['hook' => null], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(), $refusals);
    }

    public function testASubscriptionWithoutPrivilegesHoldsNone(): void
    {
        $refusals = $this->validator->validate(['hook' => 'product.written'], [], $this->subscriber, Context::createDefaultContext());

        static::assertEquals(new SubscriptionRefusals(missingPrivileges: ['hook' => ['product:read', 'order:read']]), $refusals);
    }
}

/**
 * @internal
 */
#[Package('framework')]
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
