<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\Log\Package;

/**
 * Marks a flow event that must never be delivered to a webhook. Flow Builder keeps the event;
 * only webhook delivery is withdrawn.
 *
 * @codeCoverageIgnore
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NotHookable
{
}
