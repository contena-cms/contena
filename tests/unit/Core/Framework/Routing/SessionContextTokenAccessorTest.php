<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

/**
 * @internal
 */
#[CoversClass(SessionContextTokenAccessor::class)]
class SessionContextTokenAccessorTest extends TestCase
{
    private const CHANNEL = 'a-channel';

    private const BINDING_ENABLED = ['core.systemWideLoginRegistration.isMemberBoundToChannel' => true];

    private const SUFFIXED_KEY = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . self::CHANNEL;

    public function testStartIgnoresRequestsThatAreNotFrontendRequests(): void
    {
        $request = new Request();
        $request->setSession($this->session('any'));

        $this->accessor()->start($request);

        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartMintsATokenAndStampsTheSessionId(): void
    {
        $request = $this->frontendRequest();
        $session = $this->session('a-session');
        $request->setSession($session);

        $this->accessor()->start($request);

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertSame($token, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testStartKeepsATokenTheSessionAlreadyHolds(): void
    {
        $request = $this->frontendRequest();
        $session = $this->session('a-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'already-there');
        $request->setSession($session);

        $this->accessor()->start($request);

        static::assertSame('already-there', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartAlsoStampsTheCurrentSubRequest(): void
    {
        $mainRequest = $this->frontendRequest();
        $mainRequest->setSession($this->session('a-session'));
        $subRequest = new Request();

        $this->accessor()->start($mainRequest, $subRequest);

        static::assertSame(
            $mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function testUnderBindingTheTokenIsKeptPerChannel(): void
    {
        $request = $this->frontendRequest();
        $session = $this->session('a-session');
        $session->set(self::SUFFIXED_KEY, 'channel-token');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'another-channels-token');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->start($request);

        static::assertSame('channel-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('channel-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAFrontendRequestWithoutAChannelMintsAPerRequestToken(): void
    {
        $request = new Request(attributes: [ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true]);
        $session = $this->session('a-session');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->start($request);

        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($session->has(self::SUFFIXED_KEY));
    }

    public function testReadReturnsNullWhenTheSessionIsNotDeclaredAsSource(): void
    {
        $request = $this->channelApiRequest();
        $request->headers->remove(PlatformRequest::HEADER_CONTEXT_SOURCE);

        static::assertNull($this->accessor()->read($request, self::CHANNEL));
    }

    public function testReadRejectsAnExplicitTokenAlongsideTheSessionSource(): void
    {
        $request = $this->channelApiRequest();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'an-explicit-token');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a ct-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->accessor()->read($request, self::CHANNEL);
    }

    public function testReadFailsWithoutASessionCookie(): void
    {
        $request = $this->channelApiRequest();
        $request->cookies->remove('session-');

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no frontend session cookie')
        );

        $this->accessor()->read($request, self::CHANNEL);
    }

    public function testReadFailsWhenTheCookieDoesNotResumeTheSession(): void
    {
        $request = $this->channelApiRequest();
        $request->cookies->set('session-', 'a-different-session');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this channel'
        ));

        $this->accessor()->read($request, self::CHANNEL);
    }

    public function testReadFailsWhenTheSessionHoldsNoToken(): void
    {
        $request = $this->channelApiRequest();

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this channel'
        ));

        $this->accessor()->read($request, self::CHANNEL);
    }

    public function testReadReturnsTheSessionToken(): void
    {
        $request = $this->channelApiRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);

        static::assertSame('the-sessions-token', $this->accessor()->read($request, self::CHANNEL));
    }

    public function testReadUnderBindingPrefersTheChannelKey(): void
    {
        $request = $this->channelApiRequest([
            PlatformRequest::HEADER_CONTEXT_TOKEN => 'another-channels-token',
            self::SUFFIXED_KEY => 'channel-token',
        ]);

        static::assertSame('channel-token', $this->accessor(self::BINDING_ENABLED)->read($request, self::CHANNEL));
    }

    public function testRotateReturnsFalseWithoutAResumableSession(): void
    {
        $request = new Request();

        static::assertFalse($this->accessor()->rotate($request, self::CHANNEL, 'rotated'));
    }

    public function testRotateMigratesTheFrontendSession(): void
    {
        $request = $this->frontendRequest();
        $session = $this->session('before');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'before-rotation');
        $request->setSession($session);

        static::assertTrue($this->accessor()->rotate($request, self::CHANNEL, 'rotated'));

        static::assertSame('rotated', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('rotated', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('before', $session->getId());
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testRotateMarksAChannelApiRequestAsSessionSourced(): void
    {
        $request = $this->channelApiRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'before-rotation']);

        static::assertTrue($this->accessor()->rotate($request, self::CHANNEL, 'rotated'));

        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testRotateUnderBindingWritesBothKeys(): void
    {
        $request = $this->frontendRequest();
        $session = $this->session('before');
        $request->setSession($session);

        $this->accessor(self::BINDING_ENABLED)->rotate($request, self::CHANNEL, 'rotated');

        static::assertSame('rotated', $session->get(self::SUFFIXED_KEY));
        static::assertSame('rotated', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRotateCanDestroyTheOldSessionRecord(): void
    {
        $directory = sys_get_temp_dir() . '/' . uniqid('ct-session-', true);
        $storage = new MockFileSessionStorage($directory);
        $storage->setId('before');
        $session = new Session($storage);
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'before-rotation');
        $session->save();

        $request = $this->frontendRequest();
        $request->setSession($session);

        try {
            $this->accessor()->rotate($request, self::CHANNEL, 'rotated', true);

            static::assertNotSame('before', $session->getId());
            static::assertSame([], glob($directory . '/before.mocksess') ?: []);
        } finally {
            if ($session->isStarted()) {
                $session->save();
            }
            array_map('unlink', glob($directory . '/*') ?: []);
            @rmdir($directory);
        }
    }

    /**
     * @param array<string, bool> $config
     */
    private function accessor(array $config = []): SessionContextTokenAccessor
    {
        return new SessionContextTokenAccessor(['name' => 'session-'], new StaticSystemConfigService($config));
    }

    private function session(string $id): Session
    {
        $storage = new MockArraySessionStorage();
        $storage->setId($id);

        return new Session($storage);
    }

    private function frontendRequest(): Request
    {
        return new Request(attributes: [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => self::CHANNEL,
        ]);
    }

    /**
     * @param array<string, string> $sessionData
     */
    private function channelApiRequest(array $sessionData = []): Request
    {
        $session = $this->session('a-resumable-session');
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(cookies: ['session-' => 'a-resumable-session']);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->setSession($session);

        return $request;
    }
}
