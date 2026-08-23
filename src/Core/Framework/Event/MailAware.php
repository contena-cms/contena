<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Event\EventData\MailRecipientStruct;

#[IsFlowEventAware]
interface MailAware
{
    public const string MAIL_STRUCT = 'mailStruct';

    public const string CHANNEL_ID = 'channelId';

    public const string TIMEZONE = 'timezone';

    public function getMailStruct(): MailRecipientStruct;
}
