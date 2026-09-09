<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Hmac;

use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Hmac\QuerySigner;
use Contena\Core\Framework\App\ShopId\ShopId;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(QuerySigner::class)]
class QuerySignerTest extends TestCase
{
    public function testSignUri(): void
    {
        $userId = Uuid::randomHex();

        $context = new Context(new AdminApiSource($userId));

        $localeProvider = $this->createMock(AppLocaleProvider::class);
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

        $querySigner = new QuerySigner('http://shop.url', '1.0.0', $localeProvider, $shopIdProvider, new NativeClock());
        $signedQuery = $querySigner->signUri('http://app.url/?foo=bar', $app, $context);

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('shop-id', $url);
        static::assertArrayHasKey('shop-url', $url);
        static::assertArrayHasKey('timestamp', $url);
        static::assertArrayHasKey('ct-version', $url);
        static::assertArrayHasKey('ct-context-language', $url);
        static::assertArrayHasKey('ct-user-language', $url);
        static::assertArrayHasKey('contena-shop-signature', $url);
        static::assertArrayHasKey('app-version', $url);
        static::assertArrayHasKey('ct-user-id', $url);

        static::assertSame('shopId', $url['shop-id']);
        static::assertSame('http://shop.url', $url['shop-url']);
        static::assertIsNumeric($url['timestamp']);
        static::assertSame('1.0.0', $url['ct-version']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $url['ct-context-language']);
        static::assertSame('en-GB', $url['ct-user-language']);
        static::assertSame('1.0.0', $url['app-version']);
        static::assertSame($userId, $url['ct-user-id']);
    }

    #[DataProvider('userIdDataProvider')]
    public function testUserIdInSignedUri(Context $context, string $expectedUserId): void
    {
        $localeProvider = $this->createMock(AppLocaleProvider::class);
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
            new NativeClock()
        );

        $signedQuery = $querySigner->signUri(
            'http://app.url/?foo=bar',
            $app,
            $context
        );

        \parse_str($signedQuery->getQuery(), $url);

        static::assertArrayHasKey('ct-user-id', $url);
        static::assertSame($expectedUserId, $url['ct-user-id']);
    }

    public function testThrowsWithoutAppSecret(): void
    {
        $app = new AppEntity();
        $app->setName('Foo');
        $app->setAppSecret(null);

        $querySigner = new QuerySigner(
            'http://shop.url',
            '1.0.0',
            static::createStub(AppLocaleProvider::class),
            static::createStub(ShopIdProvider::class),
            new NativeClock()
        );

        $this->expectExceptionObject(AppException::appSecretMissing('Foo'));

        $querySigner->signUri('http://app.url/?foo=bar', $app, Context::createDefaultContext());
    }

    /**
     * @return \Generator<string, array{Context, string}>
     */
    public static function userIdDataProvider(): \Generator
    {
        $userId = Uuid::randomHex();

        yield 'admin api source with user' => [
            new Context(new AdminApiSource($userId)),
            $userId,
        ];

        yield 'admin api source without user' => [
            new Context(new AdminApiSource(null)),
            '',
        ];

        yield 'non-admin api source' => [
            Context::createDefaultContext(),
            '',
        ];
    }
}
