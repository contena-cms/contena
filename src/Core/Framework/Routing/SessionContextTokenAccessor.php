<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The channel context token held in the PHP session.
 *
 * A frontend request (ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST) creates the session
 * and mints the first token. A Channel API request declaring `ct-context-source: session` may only
 * resume an existing session, never create one.
 *
 * With `core.systemWideLoginRegistration.isMemberBoundToChannel` the token lives under a
 * channel suffixed key; the plain key mirrors the channel currently browsed.
 *
 * @internal
 */
class SessionContextTokenAccessor
{
    public const CONTEXT_SOURCE_SESSION = 'session';

    /**
     * Set on Channel API requests, keeps the response out of shared caches.
     */
    public const ATTRIBUTE_TOKEN_FROM_SESSION = 'ct-context-token-from-session';

    public const SESSION_ID_KEY = 'sessionId';

    private const BINDING_CONFIG_KEY = 'core.systemWideLoginRegistration.isMemberBoundToChannel';

    private const ATTRIBUTE_SESSION_ID = 'ct-context-session-id';

    private readonly string $sessionName;

    /**
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        array $sessionOptions,
        private readonly SystemConfigService $systemConfigService
    ) {
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    public function start(Request $mainRequest, ?Request $currentRequest = null): void
    {
        if (!$this->isFrontendRequest($mainRequest)) {
            return;
        }

        /** @phpstan-ignore contena.unsafeRequestHasSession (the frontend deliberately starts its session here) */
        if (!$mainRequest->hasSession()) {
            return;
        }

        $session = $mainRequest->getSession();

        if (!$session->isStarted()) {
            $session->start();
            $session->set(self::SESSION_ID_KEY, $session->getId());
        }

        $channelId = $this->channelIdOf($mainRequest);

        // without a channel there is no token to keep, one is minted per request
        $token = $channelId === null ? null : $this->readToken($session, $channelId);

        if ($token === null) {
            $token = Random::getAlphanumericString(32);
            $this->writeToken($session, $channelId, $token);
        }

        // under binding the plain key may still hold another channel's token
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        $mainRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        if ($currentRequest !== null && $currentRequest !== $mainRequest) {
            $currentRequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
        }
    }

    /**
     * Declaring the session as context source is a contract: an unusable session fails the request
     * instead of falling back to a fresh token, which a session-based client would only see as an
     * fresh anonymous context.
     *
     * @return string|null null when the request does not declare the session as its context source
     */
    public function read(Request $request, string $channelId): ?string
    {
        if (!$this->isRequested($request)) {
            return null;
        }

        if ($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN)) {
            throw RoutingException::sessionContextNotResolvable(
                'the request also carries a ct-context-token header; declare either the session or an explicit token as context source, not both'
            );
        }

        if ($request->cookies->get($this->sessionName) === null) {
            throw RoutingException::sessionContextNotResolvable('the request carries no frontend session cookie');
        }

        $session = $this->resume($request);
        $token = null;

        if ($session !== null) {
            try {
                $token = $this->readToken($session, $channelId);
            } finally {
                $this->release($session);
            }
        }

        if ($token === null) {
            throw RoutingException::sessionContextNotResolvable(
                'the session cookie does not resume a frontend session holding a context token for this channel'
            );
        }

        return $token;
    }

    /**
     * Regenerates the session ID with every rotation and leaves the session open: Symfony's
     * AbstractSessionListener only emits the new session cookie for a session that is still started.
     *
     * @return bool whether the request holds the session and it was updated
     */
    public function rotate(Request $request, string $channelId, string $token, bool $destroyOldSession = false): bool
    {
        $session = $this->sessionFor($request);

        if ($session === null) {
            return false;
        }

        // migrate() is a no-op on a closed session, and a Channel API request's was released after the read
        if (!$session->isStarted()) {
            $session->start();
        }

        $session->migrate($destroyOldSession);
        $session->set(self::SESSION_ID_KEY, $session->getId());
        $request->attributes->set(self::ATTRIBUTE_SESSION_ID, $session->getId());
        $this->writeToken($session, $channelId, $token);

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        if (!$this->isFrontendRequest($request)) {
            $request->attributes->set(self::ATTRIBUTE_TOKEN_FROM_SESSION, true);
        }

        return true;
    }

    private function isFrontendRequest(Request $request): bool
    {
        return (bool) $request->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST);
    }

    private function isRequested(Request $request): bool
    {
        return $request->headers->get(PlatformRequest::HEADER_CONTEXT_SOURCE) === self::CONTEXT_SOURCE_SESSION;
    }

    private function sessionFor(Request $request): ?SessionInterface
    {
        if ($this->isFrontendRequest($request)) {
            return $request->hasSession(true) ? $request->getSession() : null;
        }

        return $this->resume($request);
    }

    private function resume(Request $request): ?SessionInterface
    {
        if (!$this->isRequested($request) || $request->cookies->get($this->sessionName) === null) {
            return null;
        }

        /** @phpstan-ignore contena.unsafeRequestHasSession (only reached with a session cookie, so an existing session is resumed and none created) */
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        // After a rotation, the incoming cookie still holds the old ID. Follow the ID established by
        // this request while still rejecting sessions minted by strict mode for an unknown cookie.
        $expectedId = $request->attributes->get(self::ATTRIBUTE_SESSION_ID, $request->cookies->get($this->sessionName));
        if ($session->getId() !== $expectedId) {
            $this->release($session);

            return null;
        }

        return $session;
    }

    private function channelIdOf(Request $request): ?string
    {
        $channelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);

        if ($channelId === null) {
            $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

            if ($context instanceof ChannelContext) {
                $channelId = $context->getChannelId();
            }
        }

        return \is_string($channelId) && $channelId !== '' ? $channelId : null;
    }

    private function tokenKey(?string $channelId): string
    {
        if ($channelId !== null && $this->systemConfigService->getBool(self::BINDING_CONFIG_KEY)) {
            return PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
        }

        return PlatformRequest::HEADER_CONTEXT_TOKEN;
    }

    private function readToken(SessionInterface $session, ?string $channelId): ?string
    {
        $token = $session->get($this->tokenKey($channelId));

        return \is_string($token) && $token !== '' ? $token : null;
    }

    private function writeToken(SessionInterface $session, ?string $channelId, string $token): void
    {
        $session->set($this->tokenKey($channelId), $token);
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $token);
    }

    /**
     * The native save handler locks the session for as long as it is open.
     */
    private function release(SessionInterface $session): void
    {
        if ($session->isStarted()) {
            $session->save();
        }
    }
}
