<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigService;

class DeleteUnusedGuestUserService
{
    final public const DELETE_CUSTOMERS_BATCH_SIZE = 100;

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function countUnusedUsers(Context $context): int
    {
        $maxLifeTime = $this->getUnusedGuestUserLifeTime();

        if (!$maxLifeTime) {
            return 0;
        }

        $criteria = $this->getUnusedUserCriteria($maxLifeTime);

        $criteria->addAggregation(new CountAggregation('user-count', 'id'));

        $aggregation = $this->userRepository->aggregate($criteria, $context)->get('user-count');

        return $aggregation instanceof CountResult ? $aggregation->getCount() : 0;
    }

    /**
     * @return list<array{id: string}>
     */
    public function deleteUnusedUsers(Context $context): array
    {
        $maxLifeTime = $this->getUnusedGuestUserLifeTime();

        if (!$maxLifeTime) {
            return [];
        }

        $criteria = $this->getUnusedUserCriteria($maxLifeTime)
            ->setLimit(self::DELETE_CUSTOMERS_BATCH_SIZE);

        $ids = $this->userRepository->searchIds($criteria, $context)->getPrimaryKeyData();

        $this->userRepository->delete($ids, $context);

        return $ids;
    }

    private function getUnusedUserCriteria(\DateTime $maxLifeTime): Criteria
    {
        $criteria = (new Criteria())
            ->addAssociation('orderUsers')
            ->addFilter(new AndFilter([
                new EqualsFilter('guest', true),
                new EqualsFilter('orderUsers.id', null),
                new RangeFilter(
                    'createdAt',
                    [
                        RangeFilter::LTE => $maxLifeTime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                )]));

        return $criteria;
    }

    private function getUnusedGuestUserLifeTime(): ?\DateTime
    {
        $maxLifeTime = $this->systemConfigService->getInt(
            'core.loginRegistration.unusedGuestUserLifetime'
        );

        if ($maxLifeTime <= 0) {
            return null;
        }

        return new \DateTime(\sprintf('- %d seconds', $maxLifeTime));
    }
}
