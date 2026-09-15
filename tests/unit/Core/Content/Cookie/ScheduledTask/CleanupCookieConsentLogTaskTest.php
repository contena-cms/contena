<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\ScheduledTask;

use Contena\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

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

    public function testRunsOnlyWhileDecisionsAreRecorded(): void
    {
        static::assertTrue(CleanupCookieConsentLogTask::shouldRun(new ParameterBag([
            'contena.cookie_consent.log_storage' => DatabaseCookieConsentLogStorage::NAME,
        ])));
        static::assertFalse(CleanupCookieConsentLogTask::shouldRun(new ParameterBag([
            'contena.cookie_consent.log_storage' => NullCookieConsentLogStorage::NAME,
        ])));
    }

    public function testShouldRescheduleOnFailure(): void
    {
        static::assertTrue(CleanupCookieConsentLogTask::shouldRescheduleOnFailure());
    }
}
