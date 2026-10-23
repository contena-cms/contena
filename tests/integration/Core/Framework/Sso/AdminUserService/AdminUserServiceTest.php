<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso\AdminUserService;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;
use Contena\Core\Framework\Sso\AdminUserService\ExternalAuthUser;
use Contena\Core\Framework\Sso\AdminUserService\Token;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\Framework\Sso\TokenService\TokenResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class AdminUserServiceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private AdminUserService $adminUserService;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);

        $this->admin_userService = (new ValidUserServiceCreator('create'))->create(
            $this->connection,
            static::getContainer()->get('admin_user.repository'),
        );
    }

    public function testGetAndUpdateUserByExternalTokenWithoutTokenUser(): void
    {
        $adminUserId = Uuid::randomHex();
        $email = 'test@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, $email);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->admin_userService->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($adminUserId, $externalAuthUser->admin_userId);
        static::assertSame($refreshToken, $externalAuthUser->token?->refreshToken);
        static::assertSame($email, $externalAuthUser->email);

        // ensure data is created and updated
        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('token', $tokenUserData);
        static::assertArrayHasKey('admin_user_sub', $tokenUserData);
        static::assertArrayHasKey('admin_user_id', $tokenUserData);

        $tokenObject = $tokenUserData['token'];
        static::assertInstanceOf(Token::class, $tokenObject);

        static::assertSame($token, $tokenObject->token);
        static::assertSame($refreshToken, $tokenObject->refreshToken);
        static::assertSame($subject, $tokenUserData['admin_user_sub']);
        static::assertSame($adminUserId, Uuid::fromBytesToHex($tokenUserData['admin_user_id']));

        // check admin_user is activated
        $adminUser = $this->getContainer()->get('admin_user.repository')->search(new Criteria([$externalAuthUser->admin_userId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(AdminUserEntity::class, $adminUser);
        static::assertTrue($adminUser->getActive());
        static::assertSame('given_name', $adminUser->getName());
        static::assertSame('family_name', $adminUser->getLastName());
        static::assertSame('preferred_username', $adminUser->getUsername());
    }

    public function testGetAndUpdateUserByExternalTokenWithTokenUser(): void
    {
        $adminUserId = Uuid::randomHex();
        $email = 'anotherFake@email.com';
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, $email);
        $fakeUserInstaller->installTokenUser($adminUserId, $subject);

        $idToken = (new FakeTokenGenerator())->setEmail($email)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->admin_userService->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($adminUserId, $externalAuthUser->admin_userId);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($email, $externalAuthUser->email);

        $tokenUserData = $this->getTokenUserData($subject);
        static::assertIsArray($tokenUserData);
        static::assertArrayHasKey('token', $tokenUserData);
        static::assertArrayHasKey('admin_user_sub', $tokenUserData);
        static::assertArrayHasKey('admin_user_id', $tokenUserData);

        $tokenObject = $tokenUserData['token'];
        static::assertInstanceOf(Token::class, $tokenObject);

        static::assertSame($token, $tokenObject->token);
        static::assertSame($refreshToken, $tokenObject->refreshToken);
        static::assertSame($subject, $tokenUserData['admin_user_sub']);
        static::assertSame($adminUserId, Uuid::fromBytesToHex($tokenUserData['admin_user_id']));
    }

    public function testUserEmailIsUpdated(): void
    {
        $tokenEmail = 'token@email.com';
        $localeEmail = 'locale@email.com';

        $adminUserId = Uuid::randomHex();
        $subject = Uuid::randomHex();

        $fakeUserInstaller = new FakeUserInstaller($this->connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, $localeEmail);
        $fakeUserInstaller->installTokenUser($adminUserId, $subject);

        $idToken = (new FakeTokenGenerator())->setEmail($tokenEmail)->setSubject($subject)->generate(JwksIds::KEY_ID_ONE);
        $token = Uuid::randomHex();
        $refreshToken = Uuid::randomHex();

        $tokenResult = TokenResult::createFromResponse(\json_encode([
            'id_token' => $idToken,
            'access_token' => $token,
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'any',
        ], \JSON_THROW_ON_ERROR));

        $externalAuthUser = $this->admin_userService->getAndUpdateUserByExternalToken($tokenResult);
        static::assertSame($adminUserId, $externalAuthUser->admin_userId);
        static::assertSame($token, $externalAuthUser->token?->token);
        static::assertSame($refreshToken, $externalAuthUser->token->refreshToken);
        static::assertSame($localeEmail, $externalAuthUser->email);

        $adminUser = $this->getContainer()->get('admin_user.repository')->search(new Criteria([$adminUserId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(AdminUserEntity::class, $adminUser);
        static::assertSame($tokenEmail, $adminUser->getEmail());
    }

    public function testGetRefreshedExternalTokenForUserWithoutAuthUserShouldThrowException(): void
    {
        $this->expectExceptionObject(SsoException::tokenNotFound());

        $this->admin_userService->getRefreshedExternalTokenForUser(Uuid::randomHex());
    }

    public function testGetRefreshedExternalTokenForUserWithoutTokenShouldThrowException(): void
    {
        $connection = $this->connection;
        $adminUserId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($adminUserId, Uuid::randomHex());

        $connection->update('oauth_user', ['token' => null], ['admin_user_id' => Uuid::fromHexToBytes($adminUserId)]);

        $this->expectExceptionObject(SsoException::tokenNotFound());

        $this->admin_userService->getRefreshedExternalTokenForUser($adminUserId);
    }

    public function testGetRefreshedExternalTokenForUser(): void
    {
        $connection = $this->connection;
        $adminUserId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($adminUserId, Uuid::randomHex());

        $result = $this->admin_userService->getRefreshedExternalTokenForUser($adminUserId);

        static::assertSame('access_token', $result);
    }

    public function testRemoveExternalToken(): void
    {
        $connection = $this->connection;
        $adminUserId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($adminUserId, Uuid::randomHex());

        $testSQL = 'SELECT `token` FROM `oauth_user` WHERE `admin_user_id` = ?;';

        $check = $connection->executeQuery($testSQL, [Uuid::fromHexToBytes($adminUserId)])->fetchOne();
        static::assertSame('{"token": "invalid", "refreshToken": "invalid"}', $check);

        $this->admin_userService->removeExternalToken($adminUserId);

        $result = $connection->executeQuery($testSQL, [Uuid::fromHexToBytes($adminUserId)])->fetchOne();
        static::assertNull($result);
    }

    public function testSearchOAuthUserByUserIdShouldBeNull(): void
    {
        $result = $this->admin_userService->searchOAuthUserByUserId(Uuid::randomHex());

        static::assertNull($result);
    }

    public function testSearchOAuthUserByUserId(): void
    {
        $connection = $this->connection;
        $adminUserId = Uuid::randomHex();
        $fakeUserInstaller = new FakeUserInstaller($connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, 'test@test.com');
        $fakeUserInstaller->installTokenUser($adminUserId, Uuid::randomHex());

        $result = $this->admin_userService->searchOAuthUserByUserId($adminUserId);

        static::assertInstanceOf(ExternalAuthUser::class, $result);
        static::assertSame($adminUserId, $result->admin_userId);
    }

    public function testUpdateOAuthUserWithNewToken(): void
    {
        $id = Uuid::randomHex();
        $adminUserId = Uuid::randomHex();
        $adminUserSub = Uuid::randomHex();
        $email = 'test@example.com';
        $expiryInPast = new \DateTimeImmutable('1970-01-01 00:00:00');

        $baseUser = ExternalAuthUser::create([
            'id' => $id,
            'admin_user_id' => $adminUserId,
            'admin_user_sub' => $adminUserSub,
            'token' => [
                'token' => 'any',
                'refreshToken' => 'any',
            ],
            'expiry' => $expiryInPast,
            'email' => $email,
        ]);

        $tokenResult = TokenResult::createFromResponse(json_encode([
            'id_token' => 'newIdToken',
            'access_token' => 'newAccessToken',
            'refresh_token' => 'newRefreshToken',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'write',
        ], \JSON_THROW_ON_ERROR));

        $result = $this->admin_userService->updateOAuthUserWithNewToken($baseUser, $tokenResult);

        static::assertSame($id, $result->id);
        static::assertSame($adminUserId, $result->admin_userId);
        static::assertSame($adminUserSub, $result->sub);
        static::assertInstanceOf(Token::class, $result->token);
        static::assertSame('newAccessToken', $result->token->token);
        static::assertSame('newRefreshToken', $result->token->refreshToken);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
        static::assertGreaterThan(new \DateTimeImmutable(), $result->expiry);
    }

    public function testSaveOAuthUser(): void
    {
        $adminUserId = Uuid::randomHex();
        $expiry = (new \DateTimeImmutable())->add(new \DateInterval('PT1H'));

        $fakeUserInstaller = new FakeUserInstaller($this->connection);
        $fakeUserInstaller->installBaseUserData($adminUserId, 'foo@bar.baz');
        $fakeUserInstaller->installTokenUser($adminUserId, Uuid::randomHex());

        $savedUser = $this->admin_userService->searchOAuthUserByUserId($adminUserId);
        static::assertInstanceOf(ExternalAuthUser::class, $savedUser);

        $adminUser = ExternalAuthUser::create([
            'id' => $savedUser->id,
            'admin_user_id' => $adminUserId,
            'admin_user_sub' => Uuid::randomHex(),
            'token' => [
                'token' => 'newToken',
                'refreshToken' => 'newRefreshToken',
            ],
            'expiry' => $expiry,
            'email' => 'foo@bar.baz',
        ]);

        $this->admin_userService->saveOAuthUser($adminUser);

        $result = $this->admin_userService->searchOAuthUserByUserId($adminUserId);
        static::assertInstanceOf(ExternalAuthUser::class, $result);
        static::assertSame($adminUser->id, $result->id);
        static::assertSame($adminUser->admin_userId, $result->admin_userId);
        static::assertInstanceOf(Token::class, $result->token);
        static::assertSame('newToken', $result->token->token);
        static::assertSame('newRefreshToken', $result->token->refreshToken);
        static::assertInstanceOf(\DateTimeImmutable::class, $result->expiry);
        static::assertGreaterThan(new \DateTimeImmutable(), $result->expiry);
    }

    /**
     * @return array{id: string, admin_user_id: string, admin_user_sub: string, token: Token, expiry: string}|null
     */
    private function getTokenUserData(string $subject): ?array
    {
        $dbResult = $this->connection->createQueryBuilder()
            ->select('id', 'admin_user_id', 'admin_user_sub', 'token', 'expiry')
            ->from('oauth_user')
            ->where('admin_user_sub = :subject')
            ->setParameter('subject', $subject)
            ->executeQuery()
            ->fetchAssociative();

        if (!\is_array($dbResult)) {
            return null;
        }

        return [
            'id' => $dbResult['id'],
            'admin_user_id' => $dbResult['admin_user_id'],
            'admin_user_sub' => $dbResult['admin_user_sub'],
            'token' => Token::fromArray(\json_decode($dbResult['token'], true, flags: \JSON_THROW_ON_ERROR)),
            'expiry' => $dbResult['expiry'],
        ];
    }
}
