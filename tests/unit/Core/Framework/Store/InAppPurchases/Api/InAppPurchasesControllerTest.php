<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\InAppPurchases\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ShopApiSource;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\InAppPurchase\Api\InAppPurchasesController;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesController::class)]
class InAppPurchasesControllerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext(new AdminApiSource('test-admin_user', 'test-extension'));
    }

    public function testActiveInAppPurchasesWithIncorrectContext(): void
    {
        $this->expectExceptionObject(StoreException::invalidContextSource(AdminApiSource::class, ShopApiSource::class));

        $this->createController()->activeExtensionInAppPurchases(
            Context::createDefaultContext(new ShopApiSource('test-channel'))
        );
    }

    public function testActiveInAppPurchasesWithNoIntegrationId(): void
    {
        $this->expectExceptionObject(StoreException::missingIntegrationInContextSource(AdminApiSource::class));

        $this->createController()->activeExtensionInAppPurchases(
            $this->context = Context::createDefaultContext(new AdminApiSource('test-admin_user'))
        );
    }

    public function testActiveInAppPurchasesWithNoPurchasesShouldReturnEmptyArray(): void
    {
        $response = $this->createController()->activeExtensionInAppPurchases($this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(
            [
                'inAppPurchases' => [],
                'encodedInAppPurchases' => [],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testActiveInAppPurchasesWithPurchasesShouldReturnArrayWithApps(): void
    {
        $controller = $this->createController(['test-extension' => ['purchase1', 'purchase2']]);

        $response = $controller->activeExtensionInAppPurchases($this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(
            [
                'inAppPurchases' => ['purchase1', 'purchase2'],
                'encodedInAppPurchases' => 'e7a7224d2f86ddc19057b9851032ceb0',
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)
        );

        $controller = $this->createController(['test-extension' => ['purchase1'], 'anotherExtension' => ['purchase2']]);

        $response = $controller->activeExtensionInAppPurchases($this->context);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertSame(
            [
                'inAppPurchases' => ['purchase1'],
                'encodedInAppPurchases' => '63589da1885d77a78fec9363d16d72da',
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCheckInAppPurchaseActiveWithoutRequiredParameterThrowsError(): void
    {
        $this->expectExceptionObject(StoreException::missingRequestParameter('identifier'));

        $request = new RequestDataBag();

        $this->createController()->checkExtensionInAppPurchaseIsActive($request, $this->context);
    }

    public function testCheckInAppPurchaseActiveWithNonPurchasedAppReturnsFalse(): void
    {
        $request = new RequestDataBag();
        $request->set('identifier', 'nonPurchasedApp');

        $response = $this->createController()->checkExtensionInAppPurchaseIsActive($request, $this->context);
        static::assertIsString($response->getContent());
        static::assertSame(
            ['isActive' => false],
            json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCheckInAppPurchaseActiveWithPurchasedAppReturnsTrue(): void
    {
        $request = new RequestDataBag();
        $request->set('identifier', 'purchase1');

        $controller = $this->createController(['test-extension' => ['purchase1', 'purchase2']]);

        $response = $controller->checkExtensionInAppPurchaseIsActive($request, $this->context);
        static::assertIsString($response->getContent());
        static::assertSame(
            ['isActive' => true],
            json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );

        $request->set('identifier', 'purchase2');
        $response = $controller->checkExtensionInAppPurchaseIsActive($request, $this->context);
        static::assertIsString($response->getContent());
        static::assertSame(
            ['isActive' => true],
            json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @param array<string, array<int, string>> $purchases
     */
    private function createController(array $purchases = []): InAppPurchasesController
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('test-extension');
        $repository = new StaticEntityRepository([new AppCollection([$app]), new AppCollection([$app])]);

        return new InAppPurchasesController(
            StaticInAppPurchaseFactory::createWithFeatures($purchases),
            $repository,
        );
    }
}
