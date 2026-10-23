<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\System\User\UserEvents;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class UserTokenSubscriber implements EventSubscriberInterface
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
            UserEvents::CUSTOMER_WRITTEN_EVENT => 'onUserWritten',
            UserEvents::CUSTOMER_DELETED_EVENT => 'onUserDeleted',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getResults()->only(EntityWriteResult::OPERATION_UPDATE) as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!$this->userCredentialsChanged($payload)) {
                continue;
            }

            $userId = $payload['id'];
            $newToken = $this->invalidateUsingSession($userId);

            if ($newToken) {
                $this->contextPersister->revokeAllUserTokens($userId, $newToken);
            } else {
                $this->contextPersister->revokeAllUserTokens($userId);
            }
        }
    }

    public function onUserDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $userId) {
            $this->contextPersister->revokeAllUserTokens($userId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function userCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }

    private function invalidateUsingSession(string $userId): ?string
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

        // The context user is not the same as logged-in. We don't modify the admin_user session
        if ($context->getUserId() !== $userId) {
            return null;
        }

        $newToken = $this->contextPersister->replace(
            $context->getToken(),
            $context,
        );

        $context->assign([
            'token' => $newToken,
        ]);

        // a request without a session of its own gets every token revoked
        if (!$this->sessionContextToken->rotate($mainRequest, $context->getChannelId(), $newToken)) {
            return null;
        }

        return $newToken;
    }
}
