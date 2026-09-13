<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Member\MemberEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class MemberTokenSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelContextPersister $contextPersister,
        private readonly RequestStack $requestStack,
        private readonly SessionContextTokenAccessor $sessionContextToken
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            MemberEvents::MEMBER_WRITTEN_EVENT => 'onMemberWritten',
            MemberEvents::MEMBER_DELETED_EVENT => 'onMemberDeleted',
        ];
    }

    public function onMemberWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!$this->memberCredentialsChanged($payload)) {
                continue;
            }

            $memberId = $payload['id'];
            $newToken = $this->invalidateUsingSession($memberId);

            if ($newToken) {
                $this->contextPersister->revokeAllMemberTokens($memberId, $newToken);
            } else {
                $this->contextPersister->revokeAllMemberTokens($memberId);
            }
        }
    }

    public function onMemberDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $memberId) {
            $this->contextPersister->revokeAllMemberTokens($memberId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function memberCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }

    private function invalidateUsingSession(string $memberId): ?string
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest === null) {
            return null;
        }

        $context = $mainRequest->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        // Not a frontend request
        if (!$context instanceof ChannelContext) {
            return null;
        }

        // The context member is not the same as logged-in. We don't modify the user session
        if ($context->getMemberId() !== $memberId) {
            return null;
        }

        $newToken = $this->contextPersister->replace(
            $context->getToken(),
            $context,
        );

        $context->assign([
            'token' => $newToken,
        ]);

        // A request without a session of its own gets every token revoked.
        if (!$this->sessionContextToken->rotate($mainRequest, $context->getChannelId(), $newToken)) {
            return null;
        }

        return $newToken;
    }
}
