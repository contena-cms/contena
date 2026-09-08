<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
enum WebhookDispatchDecision
{
    case Deliver;
    case Hold;
    case Skip;
}
