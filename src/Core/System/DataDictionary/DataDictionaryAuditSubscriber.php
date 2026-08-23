<?php declare(strict_types=1);

namespace Contena\Core\System\DataDictionary;

use Psr\Log\LoggerInterface;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class DataDictionaryAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $auditLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DataDictionaryEvents::DATA_DICTIONARY_WRITTEN_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_DELETED_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_TRANSLATION_WRITTEN_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_TRANSLATION_DELETED_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_WRITTEN_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_DELETED_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_TRANSLATION_WRITTEN_EVENT => 'audit',
            DataDictionaryEvents::DATA_DICTIONARY_ITEM_TRANSLATION_DELETED_EVENT => 'audit',
        ];
    }

    public function audit(EntityWrittenEvent $event): void
    {
        $this->auditLogger->info('Data dictionary entity changed.', [
            'event' => $event->getName(),
            'entity' => $event->getEntityName(),
            'ids' => $event->getIds(),
            'source' => $event->getContext()->getSource()::class,
            'tenantId' => $event->getContext()->getTenantId(),
        ]);
    }
}
