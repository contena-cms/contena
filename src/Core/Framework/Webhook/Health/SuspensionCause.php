<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * Machine-readable reason for suspending a webhook.
 *
 * @internal
 */
enum SuspensionCause: string
{
    case AuthStreak = 'auth_streak';
    case Gone = 'gone';
    case ScheduleExhausted = 'schedule_exhausted';
}
