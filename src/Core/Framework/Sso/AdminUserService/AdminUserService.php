<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\AdminUserService;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\TokenService\IdTokenParser;
use Contena\Core\Framework\Sso\TokenService\ParsedIdToken;
use Contena\Core\Framework\Sso\TokenService\TokenResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
final readonly class AdminUserService
{
    /**
     * @param EntityRepository<AdminUserCollection> $admin_userRepository
     */
    public function __construct(
        private Connection $connection,
        private IdTokenParser $idTokenParser,
        private EntityRepository $admin_userRepository,
        private ExternalTokenService $externalTokenService,
        private ClockInterface $clock,
    ) {
    }

    public function getAndUpdateUserByExternalToken(TokenResult $tokenResult): ExternalAuthUser
    {
        $context = Context::createDefaultContext();
        $parsedIdToken = $this->idTokenParser->parse($tokenResult->idToken);

        $oAuthUser = $this->searchOAuthUserBySub($parsedIdToken->sub);
        if (!$oAuthUser instanceof ExternalAuthUser) {
            // in this case we don't have an oauth_user yet,
            // we try to look for a admin_user with that mail
            $admin_user = $this->searchUserByEmail($context, $parsedIdToken->email);
            if (!$admin_user instanceof AdminUserEntity) {
                throw SsoException::admin_userNotFound($parsedIdToken->email);
            }

            // if we found a matching one we create the corresponding oauth_user with the new token
            $oAuthUser = ExternalAuthUser::create([
                'id' => Uuid::randomHex(),
                'admin_user_id' => $admin_user->getId(),
                'admin_user_sub' => $parsedIdToken->sub,
                'token' => [
                    'token' => $tokenResult->accessToken,
                    'refreshToken' => $tokenResult->refreshToken,
                ],
                'expiry' => $tokenResult->getExpiryDateTime(),
                'email' => $admin_user->getEmail(),
            ]);
            $this->insertOAuthUser($oAuthUser);

            if ($this->isInvitedUser($admin_user)) {
                // if this admin_user was previously invited, update the admin_user data to reflect that the invite was accepted
                $this->activateInvitedUser($context, $admin_user, $parsedIdToken);
            }

            return $oAuthUser;
        }

        // found an existing oauth_user, update it with new token / data
        if ($oAuthUser->email !== $parsedIdToken->email) {
            $this->updateUserEmail($context, $oAuthUser->admin_userId, $parsedIdToken->email);
        }

        $oAuthUser = $this->updateOAuthUserWithNewToken($oAuthUser, $tokenResult);
        $this->saveOAuthUser($oAuthUser);

        return $oAuthUser;
    }

    public function getRefreshedExternalTokenForUser(string $admin_userId): string
    {
        $oAuthUser = $this->searchOAuthUserByUserId($admin_userId);

        if (!$oAuthUser instanceof ExternalAuthUser || !$oAuthUser->token instanceof Token) {
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

    public function removeExternalToken(string $admin_userId): void
    {
        $this->connection->createQueryBuilder()
            ->update('oauth_user')
            ->set('token', ':token')
            ->where('admin_user_id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($admin_userId))
            ->setParameter('token', null)
            ->executeStatement();
    }

    public function searchOAuthUserByUserId(string $admin_userId): ?ExternalAuthUser
    {
        $oAuthUserData = $this->connection->createQueryBuilder()
            ->select('oauth_user.id', 'oauth_user.admin_user_id', 'oauth_user.admin_user_sub', 'oauth_user.token', 'oauth_user.expiry', 'admin_user.email')
            ->from('oauth_user', 'oauth_user')
            ->join('oauth_user', 'admin_user', 'admin_user', 'oauth_user.admin_user_id = admin_user.id')
            ->where('oauth_user.admin_user_id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($admin_userId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$oAuthUserData) {
            return null;
        }

        return ExternalAuthUser::createFromDatabaseQuery($oAuthUserData);
    }

    public function updateOAuthUserWithNewToken(ExternalAuthUser $externalAuthUser, TokenResult $tokenResult): ExternalAuthUser
    {
        return ExternalAuthUser::create([
            'id' => $externalAuthUser->id,
            'admin_user_id' => $externalAuthUser->admin_userId,
            'admin_user_sub' => $externalAuthUser->sub,
            'token' => [
                'token' => $tokenResult->accessToken,
                'refreshToken' => $tokenResult->refreshToken,
            ],
            'expiry' => $tokenResult->getExpiryDateTime(),
            'email' => $externalAuthUser->email,
        ]);
    }

    public function saveOAuthUser(ExternalAuthUser $admin_userSearchResult): void
    {
        $this->connection->update(
            'oauth_user',
            [
                'token' => \json_encode($admin_userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $admin_userSearchResult->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => Uuid::fromHexToBytes($admin_userSearchResult->id)]
        );
    }

    private function searchOAuthUserBySub(string $admin_userSubject): ?ExternalAuthUser
    {
        $oAuthUserData = $this->connection->createQueryBuilder()
            ->select('oauth_user.id', 'oauth_user.admin_user_id', 'oauth_user.admin_user_sub', 'oauth_user.token', 'oauth_user.expiry', 'admin_user.email')
            ->from('oauth_user', 'oauth_user')
            ->join('oauth_user', 'admin_user', 'admin_user', 'oauth_user.admin_user_id = admin_user.id')
            ->where('oauth_user.admin_user_sub = :sub')
            ->setParameter('sub', $admin_userSubject)
            ->executeQuery()
            ->fetchAssociative();

        if (!$oAuthUserData) {
            return null;
        }

        return ExternalAuthUser::createFromDatabaseQuery($oAuthUserData);
    }

    private function updateUserEmail(Context $context, string $admin_userId, string $newMail): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($admin_userId, $newMail): void {
            $this->admin_userRepository->update([
                [
                    'id' => $admin_userId,
                    'email' => $newMail,
                ],
            ], $context);
        });
    }

    private function insertOAuthUser(ExternalAuthUser $admin_userSearchResult): void
    {
        $this->connection->insert(
            'oauth_user',
            [
                'id' => Uuid::randomBytes(),
                'admin_user_id' => Uuid::fromHexToBytes($admin_userSearchResult->admin_userId),
                'admin_user_sub' => $admin_userSearchResult->sub,
                'token' => \json_encode($admin_userSearchResult->token, \JSON_THROW_ON_ERROR),
                'expiry' => $admin_userSearchResult->expiry->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => null,
            ],
        );
    }

    private function searchUserByEmail(Context $context, string $email): ?AdminUserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('email', $email),
        );

        return $this->admin_userRepository->search($criteria, $context)->getEntities()->first();
    }

    private function isInvitedUser(AdminUserEntity $admin_user): bool
    {
        return $admin_user->getUsername() === $admin_user->getEmail()
            && $admin_user->getFirstName() === $admin_user->getEmail()
            && $admin_user->getLastName() === $admin_user->getEmail()
            && $admin_user->getActive() === false;
    }

    private function activateInvitedUser(Context $context, AdminUserEntity $admin_userEntity, ParsedIdToken $parsedIdToken): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($admin_userEntity, $parsedIdToken): void {
            $this->admin_userRepository->update([[
                'id' => $admin_userEntity->getId(),
                'active' => true,
                'firstName' => $parsedIdToken->givenName,
                'lastName' => $parsedIdToken->familyName,
                'username' => $parsedIdToken->username,
            ]], $context);
        });
    }
}
