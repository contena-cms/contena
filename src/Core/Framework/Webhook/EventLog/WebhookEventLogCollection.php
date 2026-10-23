<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\EventLog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<WebhookEventLogEntity>
 *
 * @codeCoverageIgnore
 */
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
