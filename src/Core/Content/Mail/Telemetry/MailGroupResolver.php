<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Telemetry;


/**
 * Buckets a mail send into a small, bounded group, keyed by the triggering business event
 * (`MailService::send()`'s `$templateData['eventName']`, e.g. `checkout.order.placed`) rather than the mail
 * template: the template name is not available at the send site, and the event identifies the mail kind just
 * as well. Mails sent outside a flow (no event) resolve to `other`.
 *
 * Owns its bounded output set (closed maps, `other` as default), so the consuming metric label may use
 * `policy: open`. Known outputs: order_confirmation, order_state_change, delivery_state_change,
 * payment_state_change, user_registration, user_recovery, newsletter, contact_form, other.
 *
 * The hardcoded maps are intentional — see the rationale on
 * {@see \Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver}.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
class MailGroupResolver
{
    /**
     * Exact event name → group, for events without a usable prefix pattern.
     *
     * @var array<string, string>
     */
    private const EVENTS = [
        'checkout.order.placed' => 'order_confirmation',
        'checkout.order.payment_method.changed' => 'payment_state_change',

        'checkout.user.register' => 'user_registration',
        'checkout.user.guest_register' => 'user_registration',
        'checkout.user.double_opt_in_registration' => 'user_registration',
        'checkout.user.double_opt_in_guest_order' => 'user_registration',
        'user.group.registration.accepted' => 'user_registration',
        'user.group.registration.declined' => 'user_registration',

        'user.recovery.request' => 'user_recovery',
        'admin_user.recovery.request' => 'user_recovery',

        'contact_form.send' => 'contact_form',
        'comment_form.send' => 'contact_form',
    ];

    /**
     * State-machine → group for `state_{enter|leave}.{state_machine}.{state}` event names.
     *
     * @var array<string, string>
     */
    private const STATE_MACHINES = [
        'order' => 'order_state_change',
        'order_delivery' => 'delivery_state_change',
        'order_transaction' => 'payment_state_change',
        'order_transaction_capture_refund' => 'payment_state_change',
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
            return self::STATE_MACHINES[explode('.', $eventName)[1] ?? ''] ?? 'other';
        }

        if (str_starts_with($eventName, 'newsletter.')) {
            return 'newsletter';
        }

        return 'other';
    }
}
