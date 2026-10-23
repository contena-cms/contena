<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Api\AppJWTGenerateRoute;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\ShopId\ShopId;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Contena\Core\Test\Generator;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(AppJWTGenerateRoute::class)]
class AppJWTGenerateRouteTest extends TestCase
{
    public function testNotLoggedIn(): void
    {
        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            static::createStub(Connection::class),
            static::createStub(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            new NativeClock()
        );

        $context = Generator::generateChannelContext();
        $context->assign(['user' => null]);

        $this->expectExceptionObject(AppException::jwtGenerationRequiresUserLoggedIn());
        $appJWTGenerateRoute->generate('test', $context);
    }

    public function testNotExistingApp(): void
    {
        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            static::createStub(Connection::class),
            static::createStub(ShopIdProvider::class),
            StaticInAppPurchaseFactory::createWithFeatures(),
            new NativeClock()
        );

        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(AppException::notFound('test'));
        $appJWTGenerateRoute->generate('test', $context);
    }

    public function testGenerate(): void
    {
        $shopId = ShopId::v2('shop-id');
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider
            ->method('getShopId')
            ->willReturn($shopId);

        $inAppPurchase = StaticInAppPurchaseFactory::createWithFeatures(['extension-1' => ['purchase-1', 'purchase-2'], 'extension-2' => ['purchase-3']]);

        $privileges = [
            'channel:read',
            'user:read',
            'currency:read',
            'language:read',
            'payment_method:read',
            'shipping_method:read',
        ];

        $connection = static::createStub(Connection::class);
        $connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 'extension-1', 'app_secret' => '454545454545454545454545454544545454545', 'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR)]);

        $appJWTGenerateRoute = new AppJWTGenerateRoute(
            $connection,
            $shopIdProvider,
            $inAppPurchase,
            new NativeClock()
        );

        $context = Generator::generateChannelContext();

        $response = $appJWTGenerateRoute->generate('extension-1', $context);
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('token', $data);
        $token = $data['token'];

        $parts = explode('.', $token);
        static::assertCount(3, $parts);

        $payload = json_decode((string) base64_decode($parts[1], true), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($payload);
        static::assertArrayHasKey('channelId', $payload);
        static::assertArrayHasKey('userId', $payload);
        static::assertSame($context->getChannelId(), $payload['channelId']);
        static::assertSame($context->getUserId(), $payload['userId']);
        static::assertSame($context->getPaymentMethod()->getId(), $payload['paymentMethodId']);
        static::assertSame($context->getShippingMethod()->getId(), $payload['shippingMethodId']);
        static::assertSame($context->getCurrencyId(), $payload['currencyId']);
        static::assertSame($context->getLanguageId(), $payload['languageId']);
        static::assertSame('a6a4063ffda65516983ad40e8dc91db6', $payload['inAppPurchases']);
    }
}
