<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\TestCaseHelper;

use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Tests\Integration\Core\Framework\Routing\SessionContextTokenResolutionTest;
use PHPUnit\Framework\Assert;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ensures that Channel API integration requests do not initialize Symfony's lazy session factory.
 *
 * Symfony's AbstractSessionListener attaches the factory during kernel.request at priority 128. Frontend requests
 * deliberately initialize and start it later in SessionContextTokenSubscriber::startSession() at priority 40, while
 * Channel API requests must leave the factory uninitialized - unless they declare `ct-context-source: session`.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see SessionContextTokenResolutionTest
 */
class ChannelApiSessionListener implements EventSubscriberInterface
{
    private const SESSION_INITIALIZED = '_test_channel_api_session_initialized';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['recordSessionState', 0],
            KernelEvents::TERMINATE => 'assertSessionIsNotInitialized',
        ];
    }

    /**
     * Records the application-visible state after request handling, but before Symfony's profiler runs.
     *
     * The profiler's kernel.response listener has priority -100 and calls Request::hasPreviousSession(), which can
     * initialize the lazy factory when a session cookie exists. Recording at priority 0 prevents that test tooling
     * from being mistaken for session usage by the Channel API request itself.
     */
    public function recordSessionState(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $routeScopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        if (!\is_array($routeScopes) || !\in_array(ChannelApiRouteScope::ID, $routeScopes, true)) {
            return;
        }

        $request->attributes->set(self::SESSION_INITIALIZED, $request->hasSession(true));
    }

    /**
     * Asserts the recorded state after all response listeners have finished.
     *
     * The snapshot is used because the profiler may have initialized the session between kernel.response and
     * kernel.terminate even though application code left the Channel API request stateless.
     *
     * The assertion is intentionally deferred until kernel.terminate. An assertion thrown during kernel.response is
     * still inside HttpKernel::handle() and can be converted into an exception response. KernelBrowser calls
     * HttpKernel::terminate() after handle() returns, so the original assertion propagates directly to PHPUnit.
     */
    public function assertSessionIsNotInitialized(TerminateEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->attributes->has(self::SESSION_INITIALIZED)) {
            return;
        }

        Assert::assertFalse(
            $request->attributes->getBoolean(self::SESSION_INITIALIZED),
            \sprintf('Channel API request "%s" initialized a session.', $request->getPathInfo())
        );
    }
}
