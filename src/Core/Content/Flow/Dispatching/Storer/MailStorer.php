<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Contena\Core\Framework\Event\ChannelAware;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;

class MailStorer extends FlowStorer
{
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MailAware) {
            return $stored;
        }

        if (!isset($stored[MailAware::MAIL_STRUCT])) {
            try {
                $mail = $event->getMailStruct();
                $stored[MailAware::MAIL_STRUCT] = [
                    'recipients' => $mail->getRecipients(),
                    'bcc' => $mail->getBcc(),
                    'cc' => $mail->getCc(),
                ];
            } catch (MailEventConfigurationException) {
            }
        }

        if ($event instanceof ChannelAware && !isset($stored[MailAware::CHANNEL_ID])) {
            $stored[MailAware::CHANNEL_ID] = $event->getChannelId();
        }

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if ($storable->hasStore(MailAware::CHANNEL_ID)) {
            $storable->setData(MailAware::CHANNEL_ID, $storable->getStore(MailAware::CHANNEL_ID));
        }

        $data = $storable->getStore(MailAware::MAIL_STRUCT);
        if (!\is_array($data)) {
            return;
        }

        $recipients = $data['recipients'] ?? [];
        $mail = new MailRecipientStruct(\is_array($recipients) ? $recipients : []);
        $mail->setBcc(\is_string($data['bcc'] ?? null) ? $data['bcc'] : null);
        $mail->setCc(\is_string($data['cc'] ?? null) ? $data['cc'] : null);
        $storable->setData(MailAware::MAIL_STRUCT, $mail);
    }
}
