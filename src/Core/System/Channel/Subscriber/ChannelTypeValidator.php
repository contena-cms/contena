<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Subscriber;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Channel\Exception\DefaultChannelTypeCannotBeDeleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ChannelTypeValidator implements EventSubscriberInterface
{
    private const array PROTECTED_CHANNEL_TYPE_IDS = [
        Defaults::CHANNEL_TYPE_API => true,
        Defaults::CHANNEL_TYPE_WEB => true,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidateEvent',
        ];
    }

    public function preWriteValidateEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getDeletedPrimaryKeys(ChannelTypeDefinition::ENTITY_NAME) as $primaryKey) {
            $id = Uuid::fromBytesToHex($primaryKey['id']);

            if (\array_key_exists($id, self::PROTECTED_CHANNEL_TYPE_IDS)) {
                $event->getExceptions()->add(new DefaultChannelTypeCannotBeDeleted($id));
            }
        }
    }
}
