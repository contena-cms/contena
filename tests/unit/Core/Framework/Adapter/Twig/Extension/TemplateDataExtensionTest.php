<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\ChannelRequest;
use Contena\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Doctrine\FakeConnection;
use Contena\Frontend\Controller\NavigationController;
use Contena\Frontend\Framework\Twig\NavigationInfo;
use Contena\Frontend\Framework\Twig\TemplateDataExtension;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(TemplateDataExtension::class)]
class TemplateDataExtensionTest extends TestCase
{
    public function testGetGlobalsWithoutRequest(): void
    {
        $globals = new TemplateDataExtension(
            new RequestStack(),
            true,
            new FakeConnection([])
        )->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobalsWithoutChannelContextInRequest(): void
    {
        $globals = new TemplateDataExtension(
            new RequestStack([new Request()]),
            true,
            new FakeConnection([])
        )->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobals(): void
    {
        $channelContext = Generator::generateChannelContext();
        $activeRoute = 'frontend.home.page';
        $controller = NavigationController::class;
        $themeId = Uuid::randomHex();
        $expectedMinSearchLength = 3;
        $navigationId = $channelContext->getChannel()->getNavigationCategoryId();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $channelContext,
            '_route' => $activeRoute,
            '_controller' => $controller . '::index',
            ChannelRequest::ATTRIBUTE_THEME_ID => $themeId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) use ($expectedMinSearchLength, $navigationId) {
                if (str_contains($query, 'SELECT path FROM category')) {
                    return $navigationId . '|019503b99fb57238a79d33ec1461e512|019503c1e1397116a3a7c754858927ef|';
                }
                if (str_contains($query, 'SELECT `min_search_length` FROM `blog_search_config`')) {
                    return $expectedMinSearchLength;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = new TemplateDataExtension(
            new RequestStack([$request]),
            true,
            $connection,
        )->getGlobals();

        static::assertArrayHasKey('contena', $globals);
        static::assertArrayHasKey('dateFormat', $globals['contena']);
        static::assertSame('Y-m-d\TH:i:sP', $globals['contena']['dateFormat']);
        static::assertArrayHasKey('navigation', $globals['contena']);
        $navigationInfo = $globals['contena']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($channelContext->getChannel()->getNavigationCategoryId(), $navigationInfo->id);
        // Make sure, the root category is not part of the pathIdList
        static::assertSame(['019503b99fb57238a79d33ec1461e512', '019503c1e1397116a3a7c754858927ef'], $navigationInfo->pathIdList);
        static::assertArrayHasKey('minSearchLength', $globals['contena']);
        static::assertSame($expectedMinSearchLength, $globals['contena']['minSearchLength']);
        static::assertArrayHasKey('showStagingBanner', $globals['contena']);
        static::assertTrue($globals['contena']['showStagingBanner']);
        // Consent logging is off by default, so the frontend does not send the beacon
        static::assertFalse($globals['contena']['cookieConsentLogEnabled']);

        static::assertArrayHasKey('themeId', $globals);
        static::assertSame($themeId, $globals['themeId']);

        static::assertArrayHasKey('context', $globals);
        static::assertSame($channelContext, $globals['context']);

        static::assertArrayHasKey('activeRoute', $globals);
        static::assertSame($activeRoute, $globals['activeRoute']);

        static::assertArrayHasKey('formViolations', $globals);
        static::assertNull($globals['formViolations']);
    }

    public function testLandingPageResolvesNavigationIdFromLinkedCategory(): void
    {
        $channelContext = Generator::generateChannelContext();
        $rootCategoryId = $channelContext->getChannel()->getNavigationCategoryId();

        $landingPageId = Uuid::randomHex();
        $linkedCategoryId = Uuid::randomHex();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $channelContext,
            '_route' => 'frontend.landing.page',
            'landingPageId' => $landingPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) use ($linkedCategoryId, $rootCategoryId) {
                if (str_contains($query, 'category_translation')) {
                    return $linkedCategoryId;
                }
                if (str_contains($query, 'SELECT path FROM category')) {
                    return $rootCategoryId . '|';
                }
                if (str_contains($query, 'min_search_length')) {
                    return 3;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = new TemplateDataExtension(
            new RequestStack([$request]),
            false,
            $connection,
        )->getGlobals();

        $navigationInfo = $globals['contena']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($linkedCategoryId, $navigationInfo->id);
    }

    public function testCookieConsentLogIsEnabledForEveryStorageButNone(): void
    {
        $channelContext = Generator::generateChannelContext();
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $channelContext,
            '_route' => 'frontend.home.page',
        ]);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturnCallback(static fn (string $query) => str_contains($query, 'min_search_length')
            ? 3
            : $channelContext->getChannel()->getNavigationCategoryId() . '|');

        $globals = new TemplateDataExtension(
            new RequestStack([$request]),
            false,
            $connection,
            DatabaseCookieConsentLogStorage::NAME,
        )->getGlobals();

        static::assertTrue($globals['contena']['cookieConsentLogEnabled']);
    }

    public function testLandingPageFallsBackToRootCategoryWhenNoCategoryLinked(): void
    {
        $channelContext = Generator::generateChannelContext();
        $rootCategoryId = $channelContext->getChannel()->getNavigationCategoryId();

        $landingPageId = Uuid::randomHex();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $channelContext,
            '_route' => 'frontend.landing.page',
            'landingPageId' => $landingPageId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('fetchOne')
            ->willReturnCallback(static function (string $query) {
                if (str_contains($query, 'category_translation')) {
                    return false;
                }
                if (str_contains($query, 'SELECT path FROM category')) {
                    return '';
                }
                if (str_contains($query, 'min_search_length')) {
                    return 3;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = new TemplateDataExtension(
            new RequestStack([$request]),
            false,
            $connection,
        )->getGlobals();

        $navigationInfo = $globals['contena']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($rootCategoryId, $navigationInfo->id);
    }
}
