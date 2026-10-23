<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class LicenseHostChangedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDeletesShopSecretAndLogsOutAllUsers(): void
    {
        $context = Context::createDefaultContext();

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.store.licenseHost', 'host');
        $systemConfigService->set('core.store.shopSecret', 'shop-s3cr3t');

        /** @var EntityRepository<AdminUserCollection> $admin_userRepository */
        $admin_userRepository = static::getContainer()->get('admin_user.repository');

        $admin_user = $admin_userRepository->search(new Criteria(), $context)->getEntities()->first();
        static::assertInstanceOf(AdminUserEntity::class, $admin_user);

        // We create two new admin users
        $expectedAdminUsersCount = \count($this->fetchAllAdminUsers()) + 2;

        $admin_userRepository->create([
            [
                'localeId' => $admin_user->getLocaleId(),
                'username' => 'admin2',
                'password' => TestDefaults::HASHED_PASSWORD,
                'firstName' => 'admin2',
                'lastName' => 'admin2',
                'email' => 'admin2@contena.cn',
                'storeToken' => null,
            ],
            [
                'localeId' => $admin_user->getLocaleId(),
                'username' => 'admin3',
                'password' => TestDefaults::HASHED_PASSWORD,
                'firstName' => 'admin3',
                'lastName' => 'admin3',
                'email' => 'admin3@contena.cn',
                'storeToken' => null,
            ],
        ], $context);

        $systemConfigService->set('core.store.licenseHost', 'otherhost');
        $adminUsers = $this->fetchAllAdminUsers();

        static::assertCount($expectedAdminUsersCount, $adminUsers);
        foreach ($adminUsers as $adminUser) {
            static::assertNull($adminUser['store_token']);
        }

        static::assertNull($systemConfigService->get('core.store.shopSecret'));
    }

    /**
     * @return array<array<string, string>>
     */
    private function fetchAllAdminUsers(): array
    {
        return static::getContainer()->get(Connection::class)->executeQuery(
            'SELECT * FROM admin_user'
        )->fetchAllAssociative();
    }
}
