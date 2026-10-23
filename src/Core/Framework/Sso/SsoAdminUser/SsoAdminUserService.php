<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\SsoAdminUser;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
class SsoAdminUserService
{
    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(
        private readonly EntityRepository $adminUserRepository,
    ) {
    }

    public function inviteUser(string $email, string $localeId, Context $context): void
    {
        $adminUser = $this->getUserByEmail($email, $context);

        if ($adminUser === null) {
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
            'name' => $email,
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
