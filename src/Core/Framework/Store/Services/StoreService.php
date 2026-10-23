<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Store\Struct\AccessTokenStruct;
use Contena\Core\System\AdminUser\AdminUserCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\Store\Services\StoreServiceTest
 */
class StoreService
{
    final public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    final public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    /**
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     */
    final public function __construct(private readonly EntityRepository $admin_userRepository)
    {
    }

    public function updateStoreToken(Context $context, AccessTokenStruct $accessToken): void
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $admin_userId = $contextSource->getUserId();

        $storeToken = $accessToken->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($admin_userId, $storeToken): void {
            $this->admin_userRepository->update([['id' => $admin_userId, 'storeToken' => $storeToken]], $context);
        });
    }

    public function removeStoreToken(Context $context): void
    {
        $contextSource = $context->getSource();
        \assert($contextSource instanceof AdminApiSource);
        $admin_userId = $contextSource->getUserId();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($admin_userId): void {
            $this->admin_userRepository->update([['id' => $admin_userId, 'storeToken' => null]], $context);
        });
    }
}
