<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Hmac;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Hmac\QuerySigner;
use Contena\Core\Framework\App\ShopId\ShopId;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\Authentication\LocaleProvider;
use Contena\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(QuerySigner::class)]
class QuerySignerTest extends TestCase
{
    public function testSignUri(): void
    {
        $inAppPurchase = StaticInAppPurchaseFactory::createWithFeatures(['extension-1' => ['purchase-1', 'purchase-2'], 'extension-2' => ['purchase-3']]);

        $adminUserId = Uuid::randomHex();

        $context = new Context(new AdminApiSource($adminUserId));

        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider
            ->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('en-GB');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn(ShopId::v2('shopId'));

        $app = new AppEntity();
        $app->setName('extension-1');
        $app->setAppSecret('devSecret');
        $app->setId(Uuid::randomHex());
        $app->setVersion('1.0.0');

        $querySigner = new QuerySigner('http://shop.url', '1.0.0', $localeProvider, $shopIdProvider, $inAppPurchase, new NativeClock());
        $signedQuery = $querySigner->signUri('http://app.url/?foo=bar', $app, $context);

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('shop-id', $url);
        static::assertArrayHasKey('shop-url', $url);
        static::assertArrayHasKey('timestamp', $url);
        static::assertArrayHasKey('sw-version', $url);
        static::assertArrayHasKey('in-app-purchases', $url);
        static::assertArrayHasKey('sw-context-language', $url);
        static::assertArrayHasKey('sw-admin_user-language', $url);
        static::assertArrayHasKey('contena-shop-signature', $url);
        static::assertArrayHasKey('app-version', $url);
        static::assertArrayHasKey('sw-admin_user-id', $url);

        static::assertSame('shopId', $url['shop-id']);
        static::assertSame('http://shop.url', $url['shop-url']);
        static::assertIsNumeric($url['timestamp']);
        static::assertSame('1.0.0', $url['sw-version']);
        static::assertSame('a6a4063ffda65516983ad40e8dc91db6', $url['in-app-purchases']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $url['sw-context-language']);
        static::assertSame('en-GB', $url['sw-admin_user-language']);
        static::assertSame('1.0.0', $url['app-version']);
        static::assertSame($adminUserId, $url['sw-admin_user-id']);
    }

    #[DataProvider('admin_userIdDataProvider')]
    public function testUserIdInSignedUri(Context $context, string $expectedUserId): void
    {
        $localeProvider = $this->createMock(LocaleProvider::class);
        $localeProvider
            ->expects($this->once())
            ->method('getLocaleFromContext')
            ->with($context)
            ->willReturn('en-GB');

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn(ShopId::v2('shopId'));

        $app = new AppEntity();
        $app->setName('extension-1');
        $app->setAppSecret('devSecret');
        $app->setId(Uuid::randomHex());
        $app->setVersion('1.0.0');

        $querySigner = new QuerySigner(
            'http://shop.url',
            '1.0.0',
            $localeProvider,
            $shopIdProvider,
            StaticInAppPurchaseFactory::createWithFeatures(),
            new NativeClock()
        );

        $signedQuery = $querySigner->signUri(
            'http://app.url/?foo=bar',
            $app,
            $context
        );

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('sw-admin_user-id', $url);
        static::assertSame($expectedUserId, $url['sw-admin_user-id']);
    }

    public function testThrowsWithoutAppSecret(): void
    {
        $app = new AppEntity();
        $app->setName('Foo');
        $app->setAppSecret(null);

        $querySigner = new QuerySigner(
            'http://shop.url',
            '1.0.0',
            static::createStub(LocaleProvider::class),
            static::createStub(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            new NativeClock()
        );

        $this->expectExceptionObject(AppException::appSecretMissing('Foo'));

        $querySigner->signUri('http://app.url/?foo=bar', $app, Context::createDefaultContext());
    }

    /**
     * @return \Generator<string, array{Context, string}>
     */
    public static function admin_userIdDataProvider(): \Generator
    {
        $adminUserId = Uuid::randomHex();

        yield 'admin api source with admin_user' => [
            new Context(new AdminApiSource($adminUserId)),
            $adminUserId,
        ];

        yield 'admin api source without admin_user' => [
            new Context(new AdminApiSource(null)),
            '',
        ];

        yield 'non-admin api source' => [
            Context::createDefaultContext(),
            '',
        ];
    }
}
