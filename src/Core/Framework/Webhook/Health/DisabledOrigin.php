<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * Automation must not reactivate operator-disabled webhooks.
 *
 * @internal
 */
enum DisabledOrigin: string
{
    case Operator = 'operator';
    case Escalation = 'escalation';
}
