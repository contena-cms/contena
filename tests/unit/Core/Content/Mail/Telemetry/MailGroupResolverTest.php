<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Telemetry\MailGroupResolver;

/**
 * @internal
 */
#[CoversClass(MailGroupResolver::class)]
class MailGroupResolverTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testResolve(?string $eventName, string $expected): void
    {
        static::assertSame($expected, new MailGroupResolver()->resolve($eventName));
    }

    public static function eventProvider(): \Generator
    {
        // no triggering event (mails sent outside a flow) resolve to other
        yield 'null maps to other' => [null, 'other'];
        yield 'empty string maps to other' => ['', 'other'];

        // exact event-name lookup
        yield 'member register maps to member_registration' => ['member.register', 'member_registration'];
        yield 'member double opt-in maps to member_registration' => ['member.double_opt_in_registration', 'member_registration'];
        yield 'group registration accepted maps to member_registration' => ['member.group.registration.accepted', 'member_registration'];
        yield 'group registration declined maps to member_registration' => ['member.group.registration.declined', 'member_registration'];
        yield 'member recovery request maps to member_recovery' => ['member.recovery.request', 'member_recovery'];
        yield 'user recovery request maps to member_recovery' => ['user.recovery.request', 'member_recovery'];

        // state-machine prefix resolution
        yield 'state enter maps to state_change' => ['state_enter.example.state.active', 'state_change'];
        yield 'state leave maps to state_change' => ['state_leave.example.state.inactive', 'state_change'];

        // unlisted events fall through to other
        yield 'plugin event maps to other' => ['some.plugin.event', 'other'];
    }
}
