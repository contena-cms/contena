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
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
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
        $userId = Uuid::randomHex();
        $storeToken = Uuid::randomHex();

        $data = [
            [
                'id' => $userId,
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

        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        return Context::createDefaultContext($source);
    }

    protected function getStoreTokenFromContext(Context $context): string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();

        $userId = $source->getUserId();

        if ($userId === null) {
            throw new \RuntimeException('No user id found in context');
        }

        /** @var UserCollection $users */
        $users = $this->getUserRepository()->search(new Criteria([$userId]), $context)->getEntities();

        if ($users->count() === 0) {
            throw new \RuntimeException('No user found with id ' . $userId);
        }

        $user = $users->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $token = $user->getStoreToken();
        static::assertIsString($token);

        return $token;
    }

    protected function getFrwUserTokenFromContext(Context $context): ?string
    {
        /** @var AdminApiSource $source */
        $source = $context->getSource();
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $source->getUserId()),
            new EqualsFilter('key', FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        /** @var UserConfigEntity|null $config */
        $config = static::getContainer()->get('user_config.repository')->search($criteria, $context)->getEntities()->first();

        return $config ? $config->getValue()[FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null : null;
    }

    protected function setFrwUserToken(Context $context, string $frwUserToken): void
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw new \RuntimeException('Context with AdminApiSource expected.');
        }

        static::getContainer()->get('user_config.repository')->create([
            [
                'userId' => $source->getUserId(),
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
     * @return EntityRepository<UserCollection>
     */
    protected function getUserRepository(): EntityRepository
    {
        return static::getContainer()->get('user.repository');
    }
}
