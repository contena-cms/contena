<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cookie\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Channel\AbstractCookieRoute;
use Contena\Core\Content\Cookie\Channel\CookieConsentLogRoute;
use Contena\Core\Content\Cookie\Channel\CookieRouteResponse;
use Contena\Core\Content\Cookie\CookieException;
use Contena\Core\Content\Cookie\Event\CookieConsentLoggedEvent;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CookieConsentLogRoute::class)]
class CookieConsentLogRouteTest extends TestCase
{
    private CollectingEventDispatcher $eventDispatcher;

    private CookieConsentLogRoute $route;

    protected function setUp(): void
    {
        $cookieGroup = new CookieGroup('cookie.groupRequired');
        $cookieGroup->isRequired = true;

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse(new CookieGroupCollection([$cookieGroup]), 'current-hash', 'language-id'));

        $connection = static::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (callable $callback) => $callback($connection));

        $this->eventDispatcher = new CollectingEventDispatcher();

        $this->route = new CookieConsentLogRoute(
            $cookieRoute,
            $connection,
            $this->eventDispatcher,
            new MockClock('2026-07-13 12:00:00'),
        );
    }

    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieConsentLogRoute::class));

        $this->route->getDecorated();
    }

    public function testLogDispatchesEventAndReturnsNoContent(): void
    {
        $channelContext = Generator::generateChannelContext();

        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_selected',
            'acceptedGroups' => ['cookie.groupRequired', 'cookie.groupStatistical'],
            'cookieConfigHash' => 'client-hash',
        ]));

        $response = $this->route->log($request, $channelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);

        $event = $events[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);
        static::assertSame('accept_selected', $event->consentAction);
        static::assertSame(['cookie.groupRequired', 'cookie.groupStatistical'], $event->acceptedGroups);
        static::assertSame('client-hash', $event->configHash);
        static::assertSame($channelContext->getChannelId(), $event->channelId);
        static::assertSame($channelContext->getLanguageId(), $event->languageId);
    }

    public function testLogFallsBackToCurrentHashWhenClientSendsNone(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => ['cookie.groupRequired'],
        ]));

        $this->route->log($request, Generator::generateChannelContext());

        $event = $this->eventDispatcher->getEvents()[0];
        static::assertInstanceOf(CookieConsentLoggedEvent::class, $event);
        static::assertSame('current-hash', $event->configHash);
    }

    public function testLogThrowsOnInvalidJsonBody(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be valid JSON'));

        $this->route->log(new Request(content: 'no-json{'), Generator::generateChannelContext());
    }

    public function testLogThrowsWhenBodyIsNoObject(): void
    {
        $this->expectExceptionObject(CookieException::invalidConsentLogPayload('body must be a JSON object'));

        $this->route->log(new Request(content: '"a-string"'), Generator::generateChannelContext());
    }

    public function testLogThrowsOnUnknownConsentAction(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'reject_all',
            'acceptedGroups' => [],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'consentAction must be one of: accept_all, accept_required, accept_selected',
        ));

        $this->route->log($request, Generator::generateChannelContext());
    }

    public function testLogThrowsWhenAcceptedGroupsIsMissing(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedGroups must be a list with at most 100 entries',
        ));

        $this->route->log($request, Generator::generateChannelContext());
    }

    public function testLogThrowsWhenAcceptedGroupsContainsNonStrings(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => ['cookie.groupRequired', 42],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'acceptedGroups must contain non-empty strings',
        ));

        $this->route->log($request, Generator::generateChannelContext());
    }

    public function testLogThrowsWhenConfigHashIsNoString(): void
    {
        $request = new Request(content: (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => [],
            'cookieConfigHash' => ['not' => 'a-string'],
        ]));

        $this->expectExceptionObject(CookieException::invalidConsentLogPayload(
            'cookieConfigHash must be a non-empty string',
        ));

        $this->route->log($request, Generator::generateChannelContext());
    }
}
