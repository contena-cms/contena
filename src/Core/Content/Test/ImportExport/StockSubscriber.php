<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\ImportExport;

use Contena\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class StockSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeExportRecordEvent::class => 'onExport',
        ];
    }

    public function onExport(ImportExportBeforeExportRecordEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== 'blog') {
            return;
        }

        $keys = $event->getConfig()->getMapping()->getKeys();
        if (!\in_array('stock', $keys, true)) {
            return;
        }

        $record = $event->getRecord();
        ++$record['stock'];
        $event->setRecord($record);
    }
}
