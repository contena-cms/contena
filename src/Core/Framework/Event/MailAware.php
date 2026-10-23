<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Event\EventData\MailRecipientStruct;

#[IsFlowEventAware]
interface MailAware
{
    public const MAIL_STRUCT = 'mailStruct';

    public const CHANNEL_ID = 'channelId';

    public const TIMEZONE = 'timezone';

    public function getMailStruct(): MailRecipientStruct;

    public function getChannelId(): ?string;
}
