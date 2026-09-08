<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
enum EndpointState: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
}
