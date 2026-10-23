<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Services;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Store\Services\StoreService;
use Contena\Core\Framework\Store\Struct\AccessTokenStruct;
use Contena\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
class StoreServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StoreClientBehaviour;

    private StoreService $storeService;

    protected function setUp(): void
    {
        $this->storeService = static::getContainer()->get(StoreService::class);
    }

    public function testUpdateStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $newToken = 'updated-store-token';
        $accessTokenStruct = new AccessTokenStruct(
            new ShopUserTokenStruct(
                $newToken,
                new \DateTimeImmutable()
            )
        );

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );

        $admin_user = $this->fetchUser($adminStoreContext);
        static::assertSame('updated-store-token', $admin_user?->getStoreToken());
    }

    public function testRemoveStoreToken(): void
    {
        $adminStoreContext = $this->createAdminStoreContext();

        $accessTokenStruct = new AccessTokenStruct(
            new ShopUserTokenStruct('store-token', new \DateTimeImmutable())
        );

        $this->storeService->updateStoreToken(
            $adminStoreContext,
            $accessTokenStruct
        );
        $this->storeService->removeStoreToken($adminStoreContext);

        $admin_user = $this->fetchUser($adminStoreContext);
        static::assertNotNull($admin_user);
        static::assertNull($admin_user->getStoreToken());
    }

    private function fetchUser(Context $context): ?AdminUserEntity
    {
        /** @var AdminApiSource $adminSource */
        $adminSource = $context->getSource();
        /** @var string $admin_userId */
        $admin_userId = $adminSource->getUserId();
        $criteria = new Criteria([$admin_userId]);

        return $this->getUserRepository()->search($criteria, $context)->getEntities()->first();
    }
}
