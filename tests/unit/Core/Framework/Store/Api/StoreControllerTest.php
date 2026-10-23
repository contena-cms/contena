<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Store\Api\StoreController;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(StoreController::class)]
class StoreControllerTest extends TestCase
{
    public function testLogoutDelegatesToStoreClient(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->once())
            ->method('logout')
            ->with($context);

        $admin_userRepository = static::createStub(EntityRepository::class);

        $storeController = new StoreController(
            $storeClient,
            $admin_userRepository,
            static::createStub(AbstractExtensionDataProvider::class),
        );

        $response = $storeController->logout($context);

        static::assertSame(200, $response->getStatusCode());
    }
}
