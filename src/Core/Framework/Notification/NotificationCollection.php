<?php declare(strict_types=1);

namespace Contena\Core\Framework\Notification;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Deprecation\BCChange\ClassMoved;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NotificationEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
#[ClassMoved(version: 'v6.8.0', previousClassName: 'Contena\Administration\Notification\NotificationCollection')]
class NotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return NotificationEntity::class;
    }
}
