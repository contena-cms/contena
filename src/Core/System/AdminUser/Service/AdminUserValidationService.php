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
     * @param EntityRepository<AdminUserCollection> $adminUserRepo
     */
    public function __construct(private readonly EntityRepository $adminUserRepo)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkEmailUnique(string $adminUserEmail, string $adminUserId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('email', $adminUserEmail),
                    new NotEqualsFilter('id', $adminUserId),
                ]
            )
        );

        return $this->admin_userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkUsernameUnique(string $adminUserUsername, string $adminUserId, Context $context): bool
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('username', $adminUserUsername),
                    new NotEqualsFilter('id', $adminUserId),
                ]
            )
        );

        return $this->admin_userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }
}
