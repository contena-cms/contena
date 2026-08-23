<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Telemetry;

/**
 * Buckets a mail send into a small, bounded group, keyed by the triggering business event
 * (`MailService::send()`'s `$templateData['eventName']`, e.g. `member.register`) rather than the mail
 * template: the template name is not available at the send site, and the event identifies the mail kind just
 * as well. Mails sent outside a flow (no event) resolve to `other`.
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric label may use
 * `policy: open`. Known outputs: state_change, member_registration, member_recovery, other.
 *
 * The hardcoded map is intentional - see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 */
class MailGroupResolver
{
    /**
     * Exact event name -> group, for events without a usable prefix pattern.
     *
     * @var array<string, string>
     */
    private const array EVENTS = [
        'member.register' => 'member_registration',
        'member.double_opt_in_registration' => 'member_registration',
        'member.group.registration.accepted' => 'member_registration',
        'member.group.registration.declined' => 'member_registration',

        'member.recovery.request' => 'member_recovery',
        'user.recovery.request' => 'member_recovery',
    ];

    public function resolve(?string $eventName): string
    {
        if ($eventName === null || $eventName === '') {
            return 'other';
        }

        return self::EVENTS[$eventName] ?? $this->resolveByPrefix($eventName);
    }

    private function resolveByPrefix(string $eventName): string
    {
        if (str_starts_with($eventName, 'state_enter.') || str_starts_with($eventName, 'state_leave.')) {
            return 'state_change';
        }

        return 'other';
    }
}
