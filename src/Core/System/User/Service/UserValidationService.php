<?php declare(strict_types=1);

namespace Contena\Core\System\User\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\System\User\UserCollection;

class UserValidationService
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepo
     */
    public function __construct(private readonly EntityRepository $userRepo)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkEmailUnique(string $userEmail, string $userId, Context $context): bool
    {
        $context = $this->globalIdentityContext($context);
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('email', $userEmail),
                    new NotEqualsFilter('id', $userId),
                ]
            )
        );

        return $this->userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function checkUsernameUnique(string $userUsername, string $userId, Context $context): bool
    {
        $context = $this->globalIdentityContext($context);
        $criteria = new Criteria();

        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new EqualsFilter('username', $userUsername),
                    new NotEqualsFilter('id', $userId),
                ]
            )
        );

        return $this->userRepo->searchIds($criteria, $context)->getTotal() === 0;
    }

    private function globalIdentityContext(Context $context): Context
    {
        return Context::createGlobalContext($context->getSource());
    }
}
