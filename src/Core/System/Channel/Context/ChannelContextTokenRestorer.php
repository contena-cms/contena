<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextRestoredEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Switches the channel context to a given user (login, logout, guest conversion, imitation).
 *
 * Upstream this class also merged guest carts into the user cart; Contena has no cart, so only the
 * token / context handling remains.
 */
class ChannelContextTokenRestorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly ChannelContextPersister $contextPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Restores the context by the given token. If a context with this token doesn't exist, the context
     * is created with the user id in the payload, but not in the main user_id table column.
     *
     * @internal
     */
    public function restoreByToken(string $token, string $userId, ChannelContext $currentContext): ChannelContext
    {
        $userPayload = $this->contextPersister->load(
            $token,
            $currentContext->getChannelId(),
        );

        if ($userPayload === [] || ($userPayload['permissions'] ?? []) !== []) {
            return $this->replaceContextToken($userId, $currentContext, $token);
        }

        $userContext = $this->factory->create($userPayload['token'], $currentContext->getChannelId(), $userPayload);
        if ($userPayload['expired'] ?? false) {
            $userContext = $this->replaceContextToken($userId, $userContext, $token);
        }

        return $this->enrichUserContext($userContext, $currentContext, $currentContext->getToken(), $userId);
    }

    /**
     * Restores the context by the given user id. If a context with this user id doesn't exist, a new
     * context is created that is directly referenced to the user.
     */
    public function restore(string $userId, ChannelContext $currentContext): ChannelContext
    {
        $userPayload = $this->contextPersister->load(
            $currentContext->getToken(),
            $currentContext->getChannelId(),
            $userId
        );

        if ($userPayload === [] || ($userPayload['permissions'] ?? []) !== [] || !($userPayload['expired'] ?? false) && $userPayload['token'] === $currentContext->getToken()) {
            return $this->replaceContextToken($userId, $currentContext);
        }

        $userContext = $this->factory->create($userPayload['token'], $currentContext->getChannelId(), $userPayload);
        if ($userPayload['expired'] ?? false) {
            $userContext = $this->replaceContextToken($userId, $userContext);
        }

        if (!$userContext->getDomainId()) {
            $userContext->setDomainId($currentContext->getDomainId());
        }

        return $this->enrichUserContext($userContext, $currentContext, $currentContext->getToken(), $userId);
    }

    private function replaceContextToken(?string $userId, ChannelContext $currentContext, ?string $newToken = null): ChannelContext
    {
        $originalToken = $newToken;
        if ($newToken === null) {
            $newToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);
        }

        $currentContext->assign([
            'token' => $newToken,
        ]);

        $this->contextPersister->save(
            $newToken,
            [
                'userId' => $userId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'permissions' => [],
            ],
            $currentContext->getChannelId(),
            ($originalToken === null) ? $userId : null,
        );

        // The current context may not contain the user, e.g. when all user tokens were revoked
        // by a password change. A new context is created, so events like the UserLoginEvent
        // are dispatched with a context that contains the user and the matching rule ids.
        if ($userId !== null && $currentContext->getUserId() !== $userId) {
            $currentContext = $this->createUserContext($userId, $currentContext);
        }

        $this->updateRequestState($currentContext);

        return $currentContext;
    }

    private function createUserContext(string $userId, ChannelContext $currentContext): ChannelContext
    {
        $userContext = $this->factory->create(
            $currentContext->getToken(),
            $currentContext->getChannelId(),
            [
                ChannelContextService::CUSTOMER_ID => $userId,
                ChannelContextService::LANGUAGE_ID => $currentContext->getLanguageId(),
                ChannelContextService::CURRENCY_ID => $currentContext->getCurrencyId(),
                ChannelContextService::DOMAIN_ID => $currentContext->getDomainId(),
            ]
        );

        $userContext->addState(...$currentContext->getStates());

        if ($currentContext->getImitatingUserId() !== null) {
            $userContext->setImitatingUserId($currentContext->getImitatingUserId());
        }

        return $userContext;
    }

    private function updateRequestState(ChannelContext $context): void
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        // Only synchronize an initialized frontend session. Store API requests must remain stateless.
        if (!$request->hasSession(true)) {
            return;
        }

        $session = $request->getSession();

        if (!$context->getImitatingUserId()) {
            $session->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
        } else {
            $session->set(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID, $context->getImitatingUserId());
        }
    }

    private function enrichUserContext(
        ChannelContext $userContext,
        ChannelContext $currentContext,
        string $token,
        string $userId
    ): ChannelContext {
        if (!$userContext->getDomainId()) {
            $userContext->setDomainId($currentContext->getDomainId());
        }

        $this->contextPersister->delete($currentContext->getToken(), $currentContext->getChannelId(), $userId);

        if ($currentContext->getImitatingUserId() !== $userContext->getImitatingUserId()) {
            $userContext->setImitatingUserId($currentContext->getImitatingUserId());
        }
        $this->updateRequestState($userContext);

        $this->eventDispatcher->dispatch(new ChannelContextRestoredEvent($userContext, $currentContext));

        return $userContext;
    }
}
