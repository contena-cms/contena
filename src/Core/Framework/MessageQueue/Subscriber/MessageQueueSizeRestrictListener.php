<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Subscriber;

use Contena\Core\Framework\MessageQueue\MessageQueueException;
use Contena\Core\Framework\MessageQueue\Service\MessageSizeCalculator;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

readonly class MessageQueueSizeRestrictListener
{
    /**
     * @internal
     */
    public function __construct(
        private MessageSizeCalculator $calculator,
        private bool $enforceMessageSize,
        private int $messageMaxKiBSize,
    ) {
    }

    public function __invoke(SendMessageToTransportsEvent $event): void
    {
        if (!$this->enforceMessageSize || $this->messageMaxKiBSize <= 0) {
            return;
        }

        /**
         * If the message is sent to the SyncTransport, it means that the message is not sent to any other transport so it can be ignored.
         */
        foreach ($event->getSenders() as $sender) {
            if ($sender instanceof SyncTransport) {
                return;
            }
        }

        $messageLengthInBytes = $this->calculator->size($event->getEnvelope());
        if ($messageLengthInBytes > $this->messageMaxKiBSize * 1024) {
            $messageName = $event->getEnvelope()->getMessage()::class;

            throw MessageQueueException::maxQueueMessageSizeExceeded($messageName, $messageLengthInBytes / 1024, $this->messageMaxKiBSize);
        }
    }
}
