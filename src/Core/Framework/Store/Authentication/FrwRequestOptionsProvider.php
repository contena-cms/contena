<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Authentication;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FrwRequestOptionsProvider extends AbstractStoreRequestOptionsProvider
{
    private const CONTENA_TOKEN_HEADER = 'X-Contena-Token';

    /**
     * @param EntityRepository<UserConfigCollection> $userConfigRepository
     */
    public function __construct(
        private readonly AbstractStoreRequestOptionsProvider $optionsProvider,
        private readonly EntityRepository $userConfigRepository,
    ) {
    }

    public function getAuthenticationHeader(Context $context): array
    {
        return array_filter([self::CONTENA_TOKEN_HEADER => $this->getFrwUserToken($context)]);
    }

    public function getDefaultQueryParameters(Context $context): array
    {
        return $this->optionsProvider->getDefaultQueryParameters($context);
    }

    private function getFrwUserToken(Context $context): ?string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, $context->getSource()::class);
        }

        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $contextSource->getUserId()),
            new EqualsFilter('key', FirstRunWizardService::USER_CONFIG_KEY_FRW_USER_TOKEN),
        );

        $userConfig = $this->userConfigRepository->search($criteria, $context)->getEntities()->first();

        return $userConfig === null ? null : $userConfig->getValue()[FirstRunWizardService::USER_CONFIG_VALUE_FRW_USER_TOKEN] ?? null;
    }
}
