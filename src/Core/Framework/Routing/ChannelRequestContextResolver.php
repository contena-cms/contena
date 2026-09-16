<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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
        private readonly RouteScopeRegistry $routeScopeRegistry
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

        if (!$request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            if ($this->contextTokenRequired($request)) {
                throw RoutingException::missingRequestParameter(PlatformRequest::HEADER_CONTEXT_TOKEN);
            }

            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        // $skipIfUninitialized = true is intentional: frontend sessions are started before context resolution,
        // while Channel API requests only have a lazy session factory and must remain stateless.
        $session = $request->hasSession(true) ? $request->getSession() : null;
        $session = $session?->isStarted() ? $session : null;

        // Retrieve context for current request
        $usedContextToken = (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $currencyId = $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID, '');

        $contextServiceParameters = new ChannelContextServiceParameters(
            channelId: (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID),
            token: $usedContextToken,
            languageId: $languageId !== '' ? $languageId : null,
            currencyId: $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
            domainId: $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID),
            originalContext: $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT),
            imitatingUserId: $session?->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID),
            // overwrite currency id based on request header if it is set
            overwriteCurrencyId: $currencyId !== '' ? $currencyId : null,
        );
        $context = $this->contextService->get($contextServiceParameters);

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
