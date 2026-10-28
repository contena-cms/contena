<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[IsFlowEventAware]
interface MailAware
{
    public const MAIL_STRUCT = 'mailStruct';

    public const SALES_CHANNEL_ID = 'salesChannelId';

    public const TIMEZONE = 'timezone';

    public function getMailStruct(): MailRecipientStruct;

    public function getSalesChannelId(): ?string;
}
