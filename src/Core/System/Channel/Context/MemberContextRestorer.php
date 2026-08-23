<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Event\ChannelContextRestoredEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class MemberContextRestorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $factory,
        private readonly ChannelContextPersister $contextPersister,
        private readonly ChannelRuleLoader $ruleLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function restore(string $memberId, ChannelContext $currentContext): ChannelContext
    {
        $memberPayload = $this->contextPersister->load(
            $currentContext->getToken(),
            $currentContext->getChannelId(),
            $memberId,
        );

        if ($memberPayload === []
            || ($memberPayload[ChannelContextService::PERMISSIONS] ?? []) !== []
            || (!($memberPayload['expired'] ?? false) && $memberPayload['token'] === $currentContext->getToken())
        ) {
            return $this->replaceContextToken($memberId, $currentContext);
        }

        $memberContext = $this->factory->create(
            $memberPayload['token'],
            $currentContext->getChannelId(),
            $memberPayload,
        );
        if ($memberPayload['expired'] ?? false) {
            $memberContext = $this->replaceContextToken($memberId, $memberContext);
        }

        if (!$memberContext->getDomainId()) {
            $memberContext->setDomainId($currentContext->getDomainId());
        }

        return $this->enrichMemberContext($memberContext, $currentContext, $memberId);
    }

    private function replaceContextToken(?string $memberId, ChannelContext $currentContext): ChannelContext
    {
        $newToken = $this->contextPersister->replace($currentContext->getToken(), $currentContext);

        $this->contextPersister->save(
            $newToken,
            [
                ChannelContextService::MEMBER_ID => $memberId,
                ChannelContextService::PERMISSIONS => [],
            ],
            $currentContext->getChannelId(),
            $memberId,
        );

        if ($memberId !== null && $currentContext->getMemberId() !== $memberId) {
            $currentContext = $this->createMemberContext($memberId, $currentContext);
        }

        $this->updateRequestState($currentContext);

        return $currentContext;
    }

    private function createMemberContext(string $memberId, ChannelContext $currentContext): ChannelContext
    {
        $memberContext = $this->factory->create(
            $currentContext->getToken(),
            $currentContext->getChannelId(),
            [
                ChannelContextService::MEMBER_ID => $memberId,
                ChannelContextService::LANGUAGE_ID => $currentContext->getLanguageId(),
                ChannelContextService::COUNTRY_ID => $currentContext->getCountryId(),
                ChannelContextService::DOMAIN_ID => $currentContext->getDomainId(),
            ],
        );

        $memberContext->addState(...$currentContext->getStates());

        if ($currentContext->getImitatingUserId() !== null) {
            $memberContext->setImitatingUserId($currentContext->getImitatingUserId());
        }

        $this->ruleLoader->load($memberContext);

        return $memberContext;
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

    private function enrichMemberContext(
        ChannelContext $memberContext,
        ChannelContext $currentContext,
        string $memberId,
    ): ChannelContext {
        if (!$memberContext->getDomainId()) {
            $memberContext->setDomainId($currentContext->getDomainId());
        }

        if ($currentContext->getToken() !== $memberContext->getToken()) {
            $this->contextPersister->delete($currentContext->getToken(), $currentContext->getChannelId(), $memberId);
        }

        if ($currentContext->getImitatingUserId() !== $memberContext->getImitatingUserId()) {
            $memberContext->setImitatingUserId($currentContext->getImitatingUserId());
        }

        $this->ruleLoader->load($memberContext);
        $this->updateRequestState($memberContext);

        $this->eventDispatcher->dispatch(new ChannelContextRestoredEvent($memberContext, $currentContext));

        return $memberContext;
    }
}
