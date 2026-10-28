<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Subscriber;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Contena\Core\System\SalesChannel\Exception\DefaultSalesChannelTypeCannotBeDeleted;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelTypeValidator implements EventSubscriberInterface
{
    private const PROTECTED_SALES_CHANNEL_TYPE_IDS = [
        Defaults::SALES_CHANNEL_TYPE_API => true,
        Defaults::SALES_CHANNEL_TYPE_STOREFRONT => true,
        Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON => true,
        Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE => true,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preWriteValidateEvent',
        ];
    }

    public function preWriteValidateEvent(PreWriteValidationEvent $event): void
    {
        foreach ($event->getDeletedPrimaryKeys(SalesChannelTypeDefinition::ENTITY_NAME) as $primaryKey) {
            $id = Uuid::fromBytesToHex($primaryKey['id']);

            if (\array_key_exists($id, self::PROTECTED_SALES_CHANNEL_TYPE_IDS)) {
                $event->getExceptions()->add(new DefaultSalesChannelTypeCannotBeDeleted($id));
            }
        }
    }
}
