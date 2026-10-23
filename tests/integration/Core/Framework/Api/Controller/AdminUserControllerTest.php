<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Exception\MissingPrivilegeException;
use Contena\Core\Framework\Api\OAuth\Scope\AdminUserVerifiedScope;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AdminUserControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function tearDown(): void
    {
        $this->resetBrowser();
    }

    public function testMe(): void
    {
        $url = '/api/_info/me';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $content = json_decode((string) $client->getResponse()->getContent(), true);

        static::assertArrayHasKey('attributes', $content['data']);
        static::assertSame('admin_user', $content['data']['type']);
        static::assertSame('admin@example.com', $content['data']['attributes']['email']);
        static::assertNotNull($content['data']['relationships']['avatarMedia']);
    }

    public function testCreateUser(): void
    {
        $client = $this->getBrowser();
        $data = [
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        $client->request('POST', '/api/admin_user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertArrayHasKey('errors', $content);
        static::assertSame('This access token does not have the scope "admin_user-verified" to process this Request', $content['errors'][0]['detail']);

        static::getContainer()->get(Connection::class)
            ->executeStatement('DELETE FROM admin_user WHERE email = \'admin@example.com\'');

        $this->kernelBrowser = null;
        $client = $this->getBrowser(true, [AdminUserVerifiedScope::IDENTIFIER]);
        $client->request('POST', '/api/admin_user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $adminFlag = static::getContainer()->get(Connection::class)
            ->fetchOne(
                'SELECT admin FROM admin_user WHERE username = :username',
                ['username' => 'foobar']
            );

        static::assertNotNull($adminFlag);
        static::assertSame(0, (int) $adminFlag);
    }

    public function testRemoveRoleAssignment(): void
    {
        $ids = new IdsCollection();

        $admin_user = [
            'id' => $ids->get('admin_user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [
                ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                ['id' => $ids->get('role-2'), 'name' => 'role-2'],
            ],
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$admin_user], Context::createDefaultContext());

        $client = $this->getBrowser(true, [AdminUserVerifiedScope::IDENTIFIER]);
        $client->request('DELETE', '/api/admin_user/' . $ids->get('admin_user') . '/acl-roles/' . $ids->get('role-1'));

        $response = $client->getResponse();
        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $assigned = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative(
                'SELECT LOWER(HEX(acl_role_id)) as id FROM acl_user_role WHERE admin_user_id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('admin_user'))]
            );

        $assigned = array_column($assigned, 'id');
        static::assertSame(array_values($ids->getList(['role-2'])), $assigned);
    }

    public function testAddRoleAssignment(): void
    {
        $ids = new IdsCollection();

        $admin_user = [
            'id' => $ids->get('admin_user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$admin_user], Context::createDefaultContext());

        $client = $this->getBrowser(true, [AdminUserVerifiedScope::IDENTIFIER]);
        $client->request(
            'PATCH',
            '/api/admin_user/' . $ids->get('admin_user'),
            [
                'aclRoles' => [
                    ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                    ['id' => $ids->get('role-2'), 'name' => 'role-2'],
                ],
            ]
        );

        $response = $client->getResponse();
        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));

        $assigned = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative(
                'SELECT LOWER(HEX(acl_role_id)) as id FROM acl_user_role WHERE admin_user_id = :id ORDER BY acl_role_id ASC',
                ['id' => Uuid::fromHexToBytes($ids->get('admin_user'))]
            );

        $assigned = array_column($assigned, 'id');
        $expectedIds = $ids->getList(['role-1', 'role-2']);
        sort($expectedIds);
        static::assertSame($expectedIds, $assigned);
    }

    public function testDeleteUser(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$data], Context::createDefaultContext());

        $client = $this->getBrowser();
        $client->request('DELETE', '/api/admin_user/' . $id);
        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertArrayHasKey('errors', $content);
        static::assertSame('This access token does not have the scope "admin_user-verified" to process this Request', $content['errors'][0]['detail']);

        static::getContainer()->get(Connection::class)
            ->executeStatement('DELETE FROM admin_user WHERE email = \'admin@example.com\'');

        $this->kernelBrowser = null;
        $client = $this->getBrowser(true, [AdminUserVerifiedScope::IDENTIFIER]);
        $client->request('DELETE', '/api/admin_user/' . $id);

        $response = $client->getResponse();
        $content = json_decode((string) $response->getContent(), true);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), print_r($content, true));
    }

    public function testSetOwnProfileWithPermission(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);
        $this->getBrowser()->request('PATCH', '/api/_info/me', ['firstName' => 'newName']);
        $responsePatch = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $responsePatch->getStatusCode(), (string) $responsePatch->getContent());

        $this->getBrowser()->request('GET', '/api/_info/me');
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('newName', json_decode((string) $response->getContent(), true)['data']['attributes']['firstName']);
    }

    public function testSetOwnProfileNoPermission(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], []);
        $this->getBrowser()->request('PATCH', '/api/_info/me');
        $response = $this->getBrowser()->getResponse();

        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['admin_user_change_me'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testSetOwnProfilePermissionButNotAllowedField(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);
        $this->getBrowser()->request('PATCH', '/api/_info/me', ['title' => 'newTitle']);
        $response = $this->getBrowser()->getResponse();

        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['admin_user:update'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    /**
     * A self-service profile edit accepts `avatarMedia` only as an id link. Any nested payload,
     * including one nested inside the `extensions` container, must be rejected.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('nestedAvatarMediaPayloadProvider')]
    public function testSetOwnProfileRejectsNestedAvatarMediaWrite(array $payload): void
    {
        $browser = $this->getBrowser();
        $this->authorizeBrowser($browser, [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);

        $browser->jsonRequest('PATCH', '/api/_info/me', $payload);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(
            MissingPrivilegeException::MISSING_PRIVILEGE_ERROR,
            json_decode((string) $response->getContent(), true)['errors'][0]['code']
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function nestedAvatarMediaPayloadProvider(): iterable
    {
        yield 'via avatarMedia.admin_user' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'admin_user' => ['id' => Uuid::randomHex()]],
        ]];

        yield 'via avatarMedia.avatarUsers' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'avatarUsers' => [['id' => Uuid::randomHex()]]],
        ]];

        yield 'via avatarMedia.extensions.admin_user' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'extensions' => ['admin_user' => ['id' => Uuid::randomHex()]]],
        ]];

        yield 'via avatarMedia.extensions.avatarUsers' => [[
            'avatarMedia' => ['id' => Uuid::randomHex(), 'extensions' => ['avatarUsers' => [['id' => Uuid::randomHex()]]]],
        ]];
    }

    public function testSetOwnProfileCanUpdateAvatarViaMediaAssociation(): void
    {
        $browser = $this->getBrowser();
        $this->authorizeBrowser($browser, [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);

        $browser->request('GET', '/api/_info/me');
        $me = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $admin_userId = $me['data']['id'];
        static::assertIsString($admin_userId);

        // Setting the avatar through the media association (not just avatarId) must keep working.
        $mediaId = Uuid::randomHex();
        $browser->jsonRequest('PATCH', '/api/_info/me', ['avatarMedia' => ['id' => $mediaId]]);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(
            $mediaId,
            $connection->fetchOne('SELECT LOWER(HEX(avatar_id)) FROM `admin_user` WHERE id = UNHEX(:id)', ['id' => $admin_userId]),
            'The avatar media association must remain writable for self-service profile edits.'
        );
    }

    /**
     * `avatarMedia` may only carry an id, so an extra media field must be rejected and must not
     * reach the media entity.
     */
    public function testSetOwnProfileRejectsExtraMediaFieldsInAvatarMedia(): void
    {
        $browser = $this->getBrowser();
        $this->authorizeBrowser($browser, [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);

        $mediaId = Uuid::randomHex();
        static::getContainer()->get('media.repository')->create(
            [['id' => $mediaId, 'fileName' => 'original', 'title' => 'foreign media']],
            Context::createDefaultContext()
        );

        $browser->jsonRequest('PATCH', '/api/_info/me', [
            'avatarMedia' => ['id' => $mediaId, 'fileName' => 'changed'],
        ]);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame(
            MissingPrivilegeException::MISSING_PRIVILEGE_ERROR,
            json_decode((string) $response->getContent(), true)['errors'][0]['code']
        );

        $connection = static::getContainer()->get(Connection::class);
        static::assertSame(
            'original',
            $connection->fetchOne('SELECT file_name FROM media WHERE id = UNHEX(:id)', ['id' => $mediaId]),
            'A self-service profile edit must not change the media entity.'
        );
    }

    public function testPreventChangeOfUSerWithoutPermission(): void
    {
        $ids = new IdsCollection();

        $admin_user = [
            'id' => $ids->get('admin_user'),
            'email' => 'foo@bar.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'foobar',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'aclRoles' => [],
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$admin_user], Context::createDefaultContext());

        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user_change_me']);

        $this->getBrowser()->request('PATCH', '/api/_info/me', ['firstName' => 'newName', 'id' => $ids->get('admin_user')]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPreventCreateUserWithAdminFlagAsNonAdmin(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user:create', 'admin_user:update']);
        $client = $this->getBrowser();

        $data = [
            'email' => 'escalated@example.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'escalated',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'admin' => true,
        ];

        $client->jsonRequest('POST', '/api/admin_user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPreventUpdateUserWithAdminFlagAsNonAdmin(): void
    {
        $ids = new IdsCollection();

        $admin_user = [
            'id' => $ids->get('admin_user'),
            'email' => 'target@example.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'target-admin_user',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'admin' => false,
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$admin_user], Context::createDefaultContext());

        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user:create', 'admin_user:update']);
        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/admin_user/' . $ids->get('admin_user'),
            ['admin' => true]
        );

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPreventRoleManagerFromUpdatingNestedUserWithoutUserPrivilege(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('admin_user.repository')->create([[
            'id' => $ids->get('admin_user'),
            'email' => 'target@example.com',
            'firstName' => 'Original',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'target-admin_user',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ]], Context::createDefaultContext());

        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['acl_role:create']);
        $client = $this->getBrowser();

        $client->jsonRequest('POST', '/api/acl-role', [
            'name' => 'role',
            'privileges' => [],
            'users' => [['id' => $ids->get('admin_user'), 'firstName' => 'Changed']],
        ]);

        static::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        static::assertSame(
            'Original',
            static::getContainer()->get(Connection::class)->fetchOne(
                'SELECT first_name FROM admin_user WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('admin_user'))]
            )
        );
    }

    public function testPreventUpdateUserRolesAsNonAdmin(): void
    {
        $ids = new IdsCollection();

        $admin_user = [
            'id' => $ids->get('admin_user'),
            'email' => 'target@example.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'target-admin_user',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
        ];

        static::getContainer()->get('admin_user.repository')
            ->create([$admin_user], Context::createDefaultContext());

        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], ['admin_user:update']);
        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/admin_user/' . $ids->get('admin_user'),
            ['aclRoles' => [['id' => $ids->get('role'), 'name' => 'role']]]
        );

        static::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testCreateUserWithAdminFlagAsAdmin(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('DELETE FROM admin_user WHERE email = \'admin@example.com\'');

        $this->kernelBrowser = null;
        $client = $this->getBrowser(true, [AdminUserVerifiedScope::IDENTIFIER]);

        $data = [
            'email' => 'new-admin@example.com',
            'firstName' => 'Firstname',
            'lastName' => 'Lastname',
            'password' => TestDefaults::HASHED_PASSWORD,
            'username' => 'new-admin',
            'localeId' => static::getContainer()->get(Connection::class)->fetchOne('SELECT LOWER(HEX(id)) FROM locale LIMIT 1'),
            'admin' => true,
        ];

        $client->jsonRequest('POST', '/api/admin_user', $data);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $adminFlag = static::getContainer()->get(Connection::class)
            ->fetchOne(
                'SELECT admin FROM admin_user WHERE username = :username',
                ['username' => 'new-admin']
            );

        static::assertSame(1, (int) $adminFlag);
    }

    public function testLogoutRevokesRefreshTokens(): void
    {
        $client = $this->getBrowser();
        $connection = static::getContainer()->get(Connection::class);

        $admin_userId = $connection->fetchOne('SELECT LOWER(HEX(id)) FROM admin_user WHERE email = :email', ['email' => 'admin@example.com']);
        static::assertIsString($admin_userId);

        $tokensBefore = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM refresh_token WHERE admin_user_id = UNHEX(:admin_userId)',
            ['admin_userId' => $admin_userId]
        );
        static::assertGreaterThan(0, $tokensBefore, 'Expected at least one refresh token before logout');

        $client->request('POST', '/api/_action/admin_user/logout');
        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $tokensAfter = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM refresh_token WHERE admin_user_id = UNHEX(:admin_userId)',
            ['admin_userId' => $admin_userId]
        );
        static::assertSame(0, $tokensAfter, 'Expected all refresh tokens to be revoked after logout');
    }
}
