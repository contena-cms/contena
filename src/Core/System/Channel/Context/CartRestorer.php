<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Event\ChannelContextRestoredEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CartRestorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly ChannelContextPersister $contextPersister,
        private readonly CartService $cartService,
        private readonly CartCalculator $cartCalculator,
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * This function restores the context by the given token. If a context with this token doesn't exist, the context will
     * create with the user id in the payload, but not in the main userId table column.
     * So, the context is not directly referenced to the user and will not be loaded, if the normal restore-function is used.
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
     * This function restores the context by the given user id. If a context with this user id doesn't exist, the context will
     * create with the user id in the main userId table column.
     * So, the context is directly referenced to the user.
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

    private function mergeCart(Cart $userCart, Cart $guestCart, ChannelContext $userContext): Cart
    {
        $mergeableLineItems = $guestCart->getLineItems()->filter(static fn (LineItem $item) => ($item->getQuantity() > 0 && $item->isStackable()) || !$userCart->has($item->getId()));

        $this->eventDispatcher->dispatch(new BeforeCartMergeEvent(
            $userCart,
            $guestCart,
            $mergeableLineItems,
            $userContext
        ));

        $errors = $userCart->getErrors();
        $userCart->setErrors(new ErrorCollection());

        $userCartClone = clone $userCart;
        $userCart->setErrors($errors);
        $userCartClone->setErrors($errors);

        $mergedCart = $this->cartService->add($userCart, $mergeableLineItems->getElements(), $userContext);

        $this->eventDispatcher->dispatch(new CartMergedEvent($mergedCart, $userContext, $userCartClone));

        return $mergedCart;
    }

    private function replaceContextToken(?string $userId, ChannelContext $currentContext, ?string $newToken = null): ChannelContext
    {
        $originalToken = $newToken;
        if ($newToken === null) {
            $newToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);
        } else {
            // Prevent duplicate key RDBMS errors in case the new token exists and has permissions attached.
            $this->cartPersister->delete($newToken, $currentContext);
            $this->cartPersister->replace($currentContext->getToken(), $newToken, $currentContext);
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

        $this->cartCalculator->calculateByToken($userContext->getToken(), $userContext);

        return $userContext;
    }

    private function deleteGuestContext(ChannelContext $guestContext, string $userId): void
    {
        $this->cartService->deleteCart($guestContext);
        $this->contextPersister->delete($guestContext->getToken(), $guestContext->getChannelId(), $userId);
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

        $guestCart = $this->cartService->getCart($token, $currentContext);
        $userCart = $this->cartService->getCart($userContext->getToken(), $userContext);
        $cartsAreIdentical = $token === $userContext->getToken();

        if ($guestCart->getLineItems()->count() > 0 && !$cartsAreIdentical) {
            $restoredCart = $this->mergeCart($userCart, $guestCart, $userContext);
        } else {
            $restoredCart = $this->cartService->recalculate($userCart, $userContext);
        }

        $restoredCart->addErrors(...array_values($guestCart->getErrors()->getPersistent()->getElements()));

        $this->deleteGuestContext($currentContext, $userId);

        if ($currentContext->getImitatingUserId() !== $userContext->getImitatingUserId()) {
            $userContext->setImitatingUserId($currentContext->getImitatingUserId());
        }
        $this->updateRequestState($userContext);

        $errors = $restoredCart->getErrors();
        $cartWithErrors = $this->cartCalculator->calculateByToken($restoredCart->getToken(), $userContext);
        $cartWithErrors->setErrors($errors);
        $this->cartService->setCart($cartWithErrors);

        $this->eventDispatcher->dispatch(new ChannelContextRestoredEvent($userContext, $currentContext));

        return $userContext;
    }
}
