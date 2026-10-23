<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\SsoUser;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
class SsoUserService
{
    /**
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     */
    public function __construct(
        private readonly EntityRepository $admin_userRepository,
    ) {
    }

    public function inviteUser(string $email, string $localeId, Context $context): void
    {
        $admin_user = $this->getUserByEmail($email, $context);

        if ($admin_user === null) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($email, $localeId): void {
                $this->admin_userRepository->create([$this->createInitialUserData($email, $localeId)], $context);
            });
        }
    }

    /**
     * @return array{username: string, email: string, firstName: string, lastName: string, password: string, localeId: string}
     */
    private function createInitialUserData(string $email, string $localeId): array
    {
        return [
            'username' => $email,
            'email' => $email,
            'firstName' => $email,
            'lastName' => $email,
            'password' => Random::getAlphanumericString(32),
            'localeId' => $localeId,
        ];
    }

    private function getUserByEmail(string $email, Context $context): ?AdminUserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        return $this->admin_userRepository->search($criteria, $context)->getEntities()->first();
    }
}
