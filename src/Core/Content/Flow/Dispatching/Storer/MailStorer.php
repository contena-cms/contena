<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\System\User\UserEntity;

class MailStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MailAware) {
            return $stored;
        }

        if (!isset($stored[MailAware::MAIL_STRUCT])) {
            try {
                $mailStruct = $event->getMailStruct();
                $data = [
                    'recipients' => $mailStruct->getRecipients(),
                    'bcc' => $mailStruct->getBcc(),
                    'cc' => $mailStruct->getCc(),
                ];

                $stored[MailAware::MAIL_STRUCT] = $data;
            } catch (MailEventConfigurationException) {
            }
        }

        if (isset($stored[MailAware::CHANNEL_ID])) {
            return $stored;
        }

        $stored[MailAware::CHANNEL_ID] = $event->getChannelId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if ($storable->hasStore(MailAware::MAIL_STRUCT)) {
            $this->restoreMailStore($storable);

            return;
        }

        if ($storable->hasData(OrderAware::ORDER)) {
            $this->restoreOrderData($storable);

            return;
        }

        if ($storable->hasData(UserAware::CUSTOMER)) {
            $this->restoreUserData($storable);
        }
    }

    private function restoreMailStore(StorableFlow $storable): void
    {
        $mailStructData = $storable->getStore(MailAware::MAIL_STRUCT);

        $mailStruct = new MailRecipientStruct($mailStructData['recipients'] ?? []);
        $mailStruct->setBcc($mailStructData['bcc'] ?? null);
        $mailStruct->setCc($mailStructData['cc'] ?? null);

        $storable->setData(MailAware::CHANNEL_ID, $storable->getStore(MailAware::CHANNEL_ID));
        $storable->setData(MailAware::MAIL_STRUCT, $mailStruct);
    }

    private function restoreOrderData(StorableFlow $storable): void
    {
        /** @var OrderEntity|null $order */
        $order = $storable->getData(OrderAware::ORDER);
        if (!$order) {
            return;
        }

        $user = $order->getOrderUser();
        if (!$user) {
            return;
        }

        $storable->setData(MailAware::CHANNEL_ID, $order->getChannelId());
        $mailStruct = new MailRecipientStruct([$user->getEmail() => $user->getName()]);
        $storable->setData(MailAware::MAIL_STRUCT, $mailStruct);
    }

    private function restoreUserData(StorableFlow $storable): void
    {
        /** @var UserEntity|null $user */
        $user = $storable->getData(UserAware::CUSTOMER);
        if (!$user) {
            return;
        }

        $storable->setData(MailAware::CHANNEL_ID, $user->getChannelId());
        $mailStruct = new MailRecipientStruct([$user->getEmail() => $user->getName()]);
        $storable->setData(MailAware::MAIL_STRUCT, $mailStruct);
    }
}
