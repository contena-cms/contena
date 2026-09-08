<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Health;

/**
 * @internal
 */
enum ErrorClassification: string
{
    case Success = 'success';
    case TransientNetwork = 'transient_network';
    case TransientServer = 'transient_server';
    case TransientRateLimit = 'transient_rate_limit';
    case TransientRedirect = 'transient_redirect';
    case NonTransientPayload = 'non_transient_payload';
    case NonTransientAuth = 'non_transient_auth';
    case NonTransientEndpoint = 'non_transient_endpoint';

    public function isTransient(): bool
    {
        return match ($this) {
            self::TransientNetwork, self::TransientServer, self::TransientRateLimit, self::TransientRedirect => true,
            default => false,
        };
    }
}
