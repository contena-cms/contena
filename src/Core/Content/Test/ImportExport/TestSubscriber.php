<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\ImportExport;

use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'onImportRecordEvent',
            ImportExportAfterImportRecordEvent::class => 'onImportRecordEvent',
            ImportExportExceptionImportRecordEvent::class => 'onImportRecordEvent',
        ];
    }

    public function onImportRecordEvent(Event $event): void
    {
        // will be called on import record event
    }
}
