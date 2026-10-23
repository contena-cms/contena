<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Authentication;

use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Store\Services\InstanceService;
use Contena\Core\Framework\Store\StoreException;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
class StoreRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    final public const CONFIG_KEY_STORE_LICENSE_DOMAIN = 'core.store.licenseHost';
    final public const CONFIG_KEY_STORE_SHOP_SECRET = 'core.store.shopSecret';

    final public const CONTENA_PLATFORM_TOKEN_HEADER = 'X-Contena-Platform-Token';
    final public const CONTENA_SHOP_SECRET_HEADER = 'X-Contena-Shop-Secret';

    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(
        private readonly EntityRepository $adminUserRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly InstanceService $instanceService,
        private readonly LocaleProvider $localeProvider,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([
            self::CONTENA_PLATFORM_TOKEN_HEADER => $this->getUserStoreToken($context),
            self::CONTENA_SHOP_SECRET_HEADER => $this->systemConfigService->getString(self::CONFIG_KEY_STORE_SHOP_SECRET),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultQueryParameters(Context $context): array
    {
        return [
            'contenaVersion' => $this->instanceService->getContenaVersion(),
            'language' => $this->localeProvider->getLocaleFromContext($context),
            'domain' => $this->getLicenseDomain(),
        ];
    }

    private function getUserStoreToken(Context $context): ?string
    {
        try {
            return $this->getTokenFromAdmin($context);
        } catch (InvalidContextSourceException) {
            return $this->getTokenFromSystem($context);
        }
    }

    private function getTokenFromAdmin(Context $context): ?string
    {
        $contextSource = $this->ensureAdminApiSource($context);
        $adminUserId = $contextSource->getUserId();
        if ($adminUserId === null) {
            throw StoreException::invalidContextSourceUser($contextSource::class);
        }

        return $this->fetchUserStoreToken(new Criteria([$adminUserId]), $context);
    }

    private function getTokenFromSystem(Context $context): ?string
    {
        $contextSource = $context->getSource();
        if (!$contextSource instanceof SystemSource) {
            throw StoreException::invalidContextSource(SystemSource::class, $contextSource::class);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('storeToken', null));

        return $this->fetchUserStoreToken($criteria, $context);
    }

    private function fetchUserStoreToken(Criteria $criteria, Context $context): ?string
    {
        return $this->adminUserRepository->search($criteria, $context)->getEntities()->first()?->getStoreToken();
    }

    private function getLicenseDomain(): string
    {
        return $this->systemConfigService->getString(self::CONFIG_KEY_STORE_LICENSE_DOMAIN);
    }
}
