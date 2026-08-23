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
        private readonly RouteScopeRegistry $routeScopeRegistry,
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
            if ($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED)) {
                throw RoutingException::missingRequestParameter(PlatformRequest::HEADER_CONTEXT_TOKEN);
            }

            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, Random::getAlphanumericString(32));
        }

        // Frontend sessions are started before context resolution; Channel API sessions remain lazy and stateless.
        $session = $request->hasSession(true) ? $request->getSession() : null;
        $session = $session?->isStarted() ? $session : null;

        $usedContextToken = (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $languageId = $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '');

        $context = $this->contextService->get(new ChannelContextServiceParameters(
            (string) $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID),
            $usedContextToken,
            $languageId !== '' ? $languageId : null,
            $request->attributes->getString(ChannelRequest::ATTRIBUTE_DOMAIN_ID) ?: null,
            $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT),
            null,
            $session?->get(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID),
        ));

        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context->getContext());
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);

        $this->validateLogin($request, $context);

        $this->eventDispatcher->dispatch(new ChannelContextResolvedEvent($context, $usedContextToken));
    }

    protected function getScopeRegistry(): RouteScopeRegistry
    {
        return $this->routeScopeRegistry;
    }

    private function validateLogin(Request $request, ChannelContext $context): void
    {
        if (!$request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED)) {
            return;
        }

        if ($context->getMember() === null) {
            throw RoutingException::channelMemberNotLoggedIn();
        }
    }
}
