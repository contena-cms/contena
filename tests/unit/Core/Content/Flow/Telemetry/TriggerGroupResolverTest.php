<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Telemetry\TriggerGroupResolver;

/**
 * @internal
 */
#[CoversClass(TriggerGroupResolver::class)]
class TriggerGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(string $eventName, string $expected): void
    {
        static::assertSame($expected, new TriggerGroupResolver()->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        yield 'member login maps to member' => ['member.login', 'member'];
        yield 'user recovery request maps to user' => ['user.recovery.request', 'user'];
        yield 'channel event maps to channel' => ['channel.updated', 'channel'];
        yield 'state enter maps to state-change' => ['state_enter.example.state.active', 'state-change'];
        yield 'state leave maps to state-change' => ['state_leave.example.state.inactive', 'state-change'];
        yield 'blog event maps to content' => ['blog.written', 'content'];
        yield 'category event maps to content' => ['category.written', 'content'];
        yield 'landing page event maps to content' => ['landing_page.written', 'content'];
        yield 'mail sent maps to other' => ['mail.sent', 'other'];
        yield 'plugin event maps to other' => ['some.plugin.event', 'other'];
    }

    public function testRepeatedResolutionReturnsSameResult(): void
    {
        $resolver = new TriggerGroupResolver();

        // memoization must be transparent: the cached second call returns the same result
        static::assertSame('user', $resolver->resolve('user.recovery.request'));
        static::assertSame('user', $resolver->resolve('user.recovery.request'));
    }
}
