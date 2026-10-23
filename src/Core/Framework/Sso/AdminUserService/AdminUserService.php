<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\AdminUserService;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\TokenService\IdTokenParser;
use Contena\Core\Framework\Sso\TokenService\ParsedIdToken;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Framework\Sso\AdminUserService\ExternalAuthAdminUser;

/**
 * @internal
 */
final readonly class AdminUserService
{
    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(
        private Connection $connection,
        private IdTokenParser $idTokenParser,
        private EntityRepository $adminUserRepository,
        private ExternalTokenService $externalTokenService,
        private ClockInterface $clock,
    ) {
    }


    public function getRefreshedExternalTokenForUser(string $adminUserId): string
    {
        $oAuthUser = $this->searchOAuthUserByUserId($adminUserId);

        if (!$oAuthUser instanceof ExternalAuthAdminUser || !$oAuthUser->token instanceof Token) {
            throw SsoException::tokenNotFound();
        }

        if ($oAuthUser->expiry >= $this->clock->now()) {
            return $oAuthUser->token->token;
        }

        // token already expired, try to fetch a new one with the refresh token
        $newOAuthToken = $this->externalTokenService->getUserTokenByRefreshToken($oAuthUser->token->refreshToken);
        $oAuthUser = $this->updateOAuthUserWithNewToken($oAuthUser, $newOAuthToken);
        $this->saveOAuthUser($oAuthUser);

        return $newOAuthToken->accessToken;
    }

    public function removeExternalToken(string $adminUserId): void
    {
        $this->connection->createQueryBuilder()
            ->update('oauth_user')
            ->set('token', ':token')
            ->where('admin_user_id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($adminUserId))
            ->setParameter('token', null)
            ->executeStatement();
    }





    private function updateUserEmail(Context $context, string $adminUserId, string $newMail): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($adminUserId, $newMail): void {
            $this->admin_userRepository->update([
                [
                    'id' => $adminUserId,
                    'email' => $newMail,
                ],
            ], $context);
        });
    }


    private function searchUserByEmail(Context $context, string $email): ?AdminUserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $email),
        );

        return $this->admin_userRepository->search($criteria, $context)->getEntities()->first();
    }

    private function isInvitedUser(AdminUserEntity $adminUser): bool
    {
        return $adminUser->getUsername() === $adminUser->getEmail()
            && $adminUser->getName() === $adminUser->getEmail()
            && $adminUser->getActive() === false;
    }

    private function activateInvitedUser(Context $context, AdminUserEntity $adminUserEntity, ParsedIdToken $parsedIdToken): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($adminUserEntity, $parsedIdToken): void {
            $this->admin_userRepository->update([[
                'id' => $adminUserEntity->getId(),
                'active' => true,
                'name' => $parsedIdToken->givenName,
                'lastName' => $parsedIdToken->familyName,
                'username' => $parsedIdToken->username,
            ]], $context);
        });
    }
}
