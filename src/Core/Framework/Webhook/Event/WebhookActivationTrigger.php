<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Event;

/**
 * What moved a webhook back to HEALTHY.
 *
 * @internal
 */
enum WebhookActivationTrigger: string
{
    case Trial = 'trial';

    case Idle = 'idle';

    case Manual = 'manual';

    case AppReset = 'app_reset';

    case AppReactivateApi = 'app_reactivate_api';
}
