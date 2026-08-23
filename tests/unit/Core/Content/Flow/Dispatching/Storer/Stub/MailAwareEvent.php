<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;

/**
 * @internal
 */
class MailAwareEvent implements FlowEventAware, MailAware
{
    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([]);
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection()
            ->add('timezone', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
