<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexingMessage;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class MemberFlowEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ChannelContextRestorer $restorer,
        private readonly EntityIndexer $memberIndexer,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MemberEvents::MEMBER_WRITTEN_EVENT => 'onMemberWritten',
        ];
    }

    public function onMemberWritten(EntityWrittenEvent $event): void
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
                    $this->dispatchMemberRegisterEvent($payload['id'], $event);
                }
            } catch (ChannelException $exception) {
                if ($exception->getErrorCode() !== ChannelException::CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION) {
                    throw $exception;
                }

                if ($context->getSource() instanceof AdminApiSource && \is_string($payload['id'])) {
                    $this->connection->delete('member', ['id' => Uuid::fromHexToBytes($payload['id'])]);
                }

                throw $exception;
            }
        }
    }

    private function dispatchMemberRegisterEvent(string $memberId, EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        $channelContext = $this->restorer->restoreByMember($memberId, $context);
        $message = new MemberIndexingMessage([$memberId], context: $context);
        $this->memberIndexer->handle($message);
        if (!$member = $channelContext->getMember()) {
            return;
        }

        $memberCreated = new MemberRegisterEvent(
            $channelContext,
            $member
        );

        $this->dispatcher->dispatch($memberCreated);
    }
}
