<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;

/**
 * @internal
 */
#[CoversClass(CleanupCookieConsentLogTask::class)]
class CleanupCookieConsentLogTaskTest extends TestCase
{
    public function testTaskName(): void
    {
        static::assertSame('cookie_consent_log.cleanup', CleanupCookieConsentLogTask::getTaskName());
    }

    public function testDefaultInterval(): void
    {
        static::assertSame(86400, CleanupCookieConsentLogTask::getDefaultInterval());
    }

    public function testShouldRescheduleOnFailure(): void
    {
        static::assertTrue(CleanupCookieConsentLogTask::shouldRescheduleOnFailure());
    }
}
