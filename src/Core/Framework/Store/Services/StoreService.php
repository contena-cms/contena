<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Services;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Struct\AccessTokenStruct;
use Contena\Core\System\User\UserCollection;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Framework\Store\Services\StoreServiceTest
 */
#[Package('checkout')]
class StoreService
{
    final public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    final public const CONFIG_KEY_STORE_LICENSE_EDITION = 'core.store.licenseEdition';

    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    final public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    public function updateStoreToken(Context $context, AccessTokenStruct $accessToken): void
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $userId = $contextSource->getUserId();

        $storeToken = $accessToken->getShopUserToken()->getToken();

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $storeToken): void {
            $this->userRepository->update([['id' => $userId, 'storeToken' => $storeToken]], $context);
        });
    }

    public function removeStoreToken(Context $context): void
    {
        $contextSource = $context->getSource();
        \assert($contextSource instanceof AdminApiSource);
        $userId = $contextSource->getUserId();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($userId): void {
            $this->userRepository->update([['id' => $userId, 'storeToken' => null]], $context);
        });
    }
}
