<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\EventLog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<WebhookEventLogEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class WebhookEventLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookEventLogEntity::class;
    }
}
