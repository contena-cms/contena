<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(TriggerGroupResolver::class)]
class TriggerGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(string $eventName, string $expected): void
    {
        static::assertSame($expected, (new TriggerGroupResolver())->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        yield 'order placed maps to order' => ['checkout.order.placed', 'order'];
        yield 'user login maps to user' => ['checkout.user.login', 'user'];
        yield 'user recovery request maps to user' => ['user.recovery.request', 'user'];
        yield 'generic checkout event maps to checkout' => ['checkout.something', 'checkout'];
        yield 'transaction state paid maps to state-change' => ['state_enter.order_transaction.state.paid', 'state-change'];
        yield 'order state open (leave) maps to state-change' => ['state_leave.order.state.open', 'state-change'];
        yield 'app installed maps to app' => ['app.installed', 'app'];
        yield 'newsletter register maps to content' => ['newsletter.register', 'content'];
        yield 'contact form send maps to content' => ['contact_form.send', 'content'];
        yield 'review form send maps to content' => ['review_form.send', 'content'];
        yield 'mail sent maps to other' => ['mail.sent', 'other'];
        yield 'plugin event maps to other' => ['some.plugin.event', 'other'];
    }

    public function testRepeatedResolutionReturnsSameResult(): void
    {
        $resolver = new TriggerGroupResolver();

        // memoization must be transparent: the cached second call returns the same result
        static::assertSame('order', $resolver->resolve('checkout.order.placed'));
        static::assertSame('order', $resolver->resolve('checkout.order.placed'));
    }
}
