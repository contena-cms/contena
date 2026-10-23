<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Store;

use GuzzleHttp\Handler\MockHandler;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
trait StoreClientBehaviour
{
    public function getStoreRequestHandler(): MockHandler
    {
        /** @var MockHandler $handler */
        $handler = static::getContainer()->get('contena.store.mock_handler');

        return $handler;
    }

    public function getFrwRequestHandler(): MockHandler
    {
        /** @var MockHandler $handler */
        $handler = static::getContainer()->get('contena.frw.mock_handler');

        return $handler;
    }

    #[After]
    #[Before]
    public function resetStoreMock(): void
    {
        $this->getStoreRequestHandler()->reset();
    }

    #[After]
    #[Before]
    public function resetFrwMock(): void
    {
        $this->getFrwRequestHandler()->reset();
    }

    protected function createAdminStoreContext(): Context
    {
        $admin_userId = Uuid::randomHex();
        $storeToken = Uuid::randomHex();

        $data = [
            [
                'id' => $admin_userId,
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'username' => 'foobar',
                'password' => 'asdasdasdasd',
                'firstName' => 'Foo',
                'lastName' => 'Bar',
                'email' => Uuid::randomHex() . '@bar.com',
                'storeToken' => $storeToken,
            ],
        ];

        $this->getUserRepository()->create($data, Context::createDefaultContext());

        $source = new AdminApiSource($admin_userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    protected function getStoreTokenFromContext(Context $context): string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $admin_userId = $source->getUserId();

        if ($admin_userId === null) {
            throw new \RuntimeException('No admin_user id found in context');
        }

        /** @var AdminUserCollection $users */
        $users = $this->getUserRepository()->search(new Criteria([$admin_userId]), $context)->getEntities();

        if ($users->count() === 0) {
            throw new \RuntimeException('No admin_user found with id ' . $admin_userId);
        }

        $admin_user = $users->first();
        static::assertInstanceOf(AdminUserEntity::class, $admin_user);

        $token = $admin_user->getStoreToken();
        static::assertIsString($token);

        return $token;
    }

    protected function getFrwUserTokenFromContext(Context $context): ?string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('admin_userId', $source->getUserId()),
            new EqualsFilter('key', FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        /** @var AdminUserConfigEntity|null $config */
        $config = static::getContainer()->get('admin_user_config.repository')->search($criteria, $context)->getEntities()->first();

        return $config ? $config->getValue()[FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null : null;
    }

    protected function setFrwUserToken(Context $context, string $frwUserToken): void
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new \RuntimeException('Context with AdminApiSource expected.');
        }

        static::getContainer()->get('admin_user_config.repository')->create([
            [
                'admin_userId' => $source->getUserId(),
                'key' => FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN,
                'value' => [
                    FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                ],
            ],
        ], Context::createDefaultContext());
    }

    protected function setLicenseDomain(?string $licenseDomain): void
    {
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->set(
            'core.store.licenseHost',
            $licenseDomain
        );
    }

    protected function setShopSecret(string $shopSecret): void
    {
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $systemConfigService->set(
            'core.store.shopSecret',
            $shopSecret
        );
    }

    protected function getContenaVersion(): string
    {
        $instanceService = static::getContainer()->get(InstanceService::class);

        return $instanceService->getContenaVersion();
    }

    /**
     * @return EntityRepository<AdminUserCollection>
     */
    protected function getUserRepository(): EntityRepository
    {
        return static::getContainer()->get('admin_user.repository');
    }
}
