<?php declare(strict_types=1);

namespace Contena\Core\Framework\Notification;

use Contena\Core\Framework\DataAbstractionLayer\BulkEntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Integration\IntegrationDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NotificationBulkEntityExtension extends BulkEntityExtension
{
    public function collect(): \Generator
    {
        yield IntegrationDefinition::ENTITY_NAME => [
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_integration_id', 'id'),
        ];

        yield UserDefinition::ENTITY_NAME => [
            new OneToManyAssociationField('createdNotifications', NotificationDefinition::class, 'created_by_user_id', 'id'),
        ];
    }
}
