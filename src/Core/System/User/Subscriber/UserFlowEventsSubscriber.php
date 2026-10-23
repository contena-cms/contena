<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexingMessage;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\UserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class UserFlowEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ChannelContextRestorer $restorer,
        private readonly EntityIndexer $userIndexer,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::CUSTOMER_WRITTEN_EVENT => 'onUserWritten',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getSource() instanceof ChannelApiSource) {
            return;
        }

        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            try {
                $createdAt = $payload['createdAt'] ?? null;
                if ($createdAt !== null && $createdAt !== '') {
                    $this->dispatchUserRegisterEvent($payload['id'], $event);
                }
            } catch (ChannelException $exception) {
                if ($exception->getErrorCode() !== ChannelException::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION) {
                    throw $exception;
                }

                if ($context->getSource() instanceof AdminApiSource && \is_string($payload['id'])) {
                    $this->connection->delete('user', ['id' => Uuid::fromHexToBytes($payload['id'])]);
                }

                throw $exception;
            }
        }
    }

    private function dispatchUserRegisterEvent(string $userId, EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        $channelContext = $this->restorer->restoreByUser($userId, $context);
        $message = new UserIndexingMessage([$userId]);
        $this->userIndexer->handle($message);
        if (!$user = $channelContext->getUser()) {
            return;
        }

        $userCreated = new UserRegisterEvent(
            $channelContext,
            $user
        );

        $this->dispatcher->dispatch($userCreated);
    }
}
