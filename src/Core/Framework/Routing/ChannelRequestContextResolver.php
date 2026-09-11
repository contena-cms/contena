<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class ChannelRequestContextResolver implements RequestContextResolverInterface
{
    use RouteScopeCheckTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestContextResolverInterface $decorated,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouteScopeRegistry $routeScopeRegistry,
        private readonly SessionContextTokenAccessor $sessionContextToken
    ) {
    }

    public function resolve(Request $request): void
    {
        if (!$request->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_ID)) {
            $this->decorated->resolve($request);

            return;
        }

        if (!$this->isRequestScoped($request, ChannelContextRouteScopeDependant::class)) {
            return;
        }

        if ($this->isRequestScoped($request, ChannelApiRouteScope::class) && $this->sessionContextToken->isRequested($request)) {
            $this->resolveContextTokenFromSession($request);
        }

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            if ($this->contextTokenRequired($request)) {
                throw RoutingException::missingRequestParameter(PlatformRequest::HEADER_CONTEXT_TOKEN);
            }

            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        // $skipIfUninitialized = true is intentional: frontend sessions are started before context resolution,
        // while Store API requests only have a lazy session factory and must remain stateless.
        $session = $request->hasSession(true) ? $request->getSession() : null;
        $session = $session?->isStarted() ? $session : null;

        // Retrieve context for current request
        $usedContextToken = (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $contextServiceParameters = new ChannelContextServiceParameters(
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID),
            $usedContextToken,
            $languageId !== '' ? $languageId : null,
            $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID),
            $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT),
            null,
            $session?->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID),
        );
        $context = $this->contextService->get($contextServiceParameters);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        // Validate if a member login is required for the current request
        $this->validateLogin($request, $context);

        $this->eventDispatcher->dispatch(
            new ChannelContextResolvedEvent($context, $usedContextToken)
        );
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    /**
     * Declaring the session as context source is a contract: an unusable session fails the request
     * instead of falling back to a fresh token, which a session based client would only see as an
     * empty cart. Frontend requests are exempt, Core itself set their token header.
     */
    private function resolveContextTokenFromSession(Request $request): void
    {
        if ($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            throw RoutingException::sessionContextNotResolvable(
                'the request also carries a ct-context-token header; declare either the session or an explicit token as context source, not both'
            );
        }

        $reason = $this->sessionContextToken->ineligibilityReason($request);

        if ($reason !== null) {
            throw RoutingException::sessionContextNotResolvable($reason);
        }

        $channelId = (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);

        $token = $this->sessionContextToken->read($request, $channelId);

        if ($token === null) {
            throw RoutingException::sessionContextNotResolvable(
                'the session cookie does not resume a frontend session holding a context token for this channel'
            );
        }

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        $request->attributes->set(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);
    }

    private function contextTokenRequired(Request $request): bool
    {
        return (bool) $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED, false);
    }

    private function validateLogin(Request $request, ChannelContext $context): void
    {
        if (!$request->attributes->get(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED)) {
            return;
        }

        if ($context->getMember() === null) {
            throw RoutingException::channelMemberNotLoggedIn();
        }
    }
}
