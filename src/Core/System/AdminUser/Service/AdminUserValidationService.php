<?php declare(strict_types=1);

namespace Contena\Core\System\AdminUser\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\System\AdminUser\AdminUserCollection;

class AdminUserValidationService
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserCollection> $admin_userRepo
     */
    public function __construct(private readonly EntityRepository $admin_userRepo)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkEmailUnique(string $admin_userEmail, string $admin_userId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('email', $admin_userEmail),
                    new NotEqualsFilter('id', $admin_userId),
                ]
            )
        );

        return $this->admin_userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkUsernameUnique(string $admin_userUsername, string $admin_userId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('username', $admin_userUsername),
                    new NotEqualsFilter('id', $admin_userId),
                ]
            )
        );

        return $this->admin_userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }
}
