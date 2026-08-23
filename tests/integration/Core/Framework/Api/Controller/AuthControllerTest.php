<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\UnencryptedToken;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AuthControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testRequiresAuthentication(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameter('HTTP_Authorization', '');
        $client->request('GET', '/api/category');
        static::assertNotFalse($client->getResponse()->getContent());

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        static::assertNotFalse($client->getResponse()->getContent());
        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_UNAUTHORIZED, (int) $response['errors'][0]['status']);
        static::assertSame(
            'The resource owner or authorization server denied the request.',
            $response['errors'][0]['title']
        );
        static::assertSame('Missing token in "Authorization" header', $response['errors'][0]['detail']);
    }

    public function testCreateTokenWithInvalidCredentials(): void
    {
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => 'contena',
            'password' => 'not_a_real_password',
        ];

        $client = $this->getBrowser();
        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        static::assertNotFalse($client->getResponse()->getContent());
        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);

        static::assertSame(OAuthServerException::invalidCredentials()->getMessage(), $response['errors'][0]['title']);
    }

    public function testAccessWithInvalidToken(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameters([
            'HTTP_Authorization' => 'Bearer invalid_token_provided',
        ]);
        $client->request('GET', '/api/category');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        static::assertNotFalse($client->getResponse()->getContent());
        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_UNAUTHORIZED, (int) $response['errors'][0]['status']);
        static::assertSame(
            'The resource owner or authorization server denied the request.',
            $response['errors'][0]['title']
        );
        static::assertSame('The JWT string must have two dots', $response['errors'][0]['detail']);
    }

    public function testAccessWithExpiredToken(): void
    {
        $client = $this->getBrowser();
        $client->setServerParameter(
            'HTTP_Authorization',
            'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0In0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6IjBkZmFhOTJkMWNkYTJiZmUyNGMwOGU4MmNhZmExMDY4N2I2ZWEzZTI0MjE4NjcxMmM0YjI3NTA4Y2NjNWQ0MzI3MWQxODYzODA1NDYwYzQ0IiwiaWF0IjoxNTI5NDM2MTkyLCJuYmYiOjE1Mjk0MzYxOTIsImV4cCI6MTUyOTQzOTc5Miwic3ViIjoiNzI2MWQyNmMzZTM2NDUxMDk1YWZhN2MwNWY4NzMyYjUiLCJzY29wZXMiOlsid3JpdGUiLCJ3cml0ZSJdfQ.DBYbAWNpwxGL6QngLidboGbr2nmlAwjYcJIqN02sRnZNNFexy9V6uyQQ-8cJ00anwxKhqBovTzHxtXBMhZ47Ix72hxNWLjauKxQlsHAbgIKBDRbJO7QxgOU8gUnSQiXzRzKoX6XBOSHXFSUJ239lF4wai7621aCNFyEvlwf1JZVILsLjVkyIBhvuuwyIPbpEETui19BBaJ0eQZtjXtpzjsWNq1ibUCQvurLACnNxmXIj8xkSNenoX5B4p3R1gbDFuxaNHkGgsrQTwkDtmZxqCb3_0AgFL3XX0mpO5xsIJAI_hLHDPvv5m0lTQgMRrlgNdfE7ecI4GLHMkDmjWoNx_A'
        );
        $client->request('GET', '/api/category');

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        static::assertNotFalse($client->getResponse()->getContent());

        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_UNAUTHORIZED, (int) $response['errors'][0]['status']);
    }

    public function testAccessProtectedResourceWithToken(): void
    {
        $this->getBrowser()->request('GET', '/api/category');
        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        static::assertSame(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $this->getBrowser()->getResponse()->getContent()
        );

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());
        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
    }

    public function testInvalidRefreshToken(): void
    {
        $client = $this->getBrowser();

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => 'foobar',
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $client->getResponse()->getStatusCode(),
            print_r($client->getResponse()->getContent(), true)
        );
        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);
        static::assertSame('The refresh token is invalid.', $response['errors'][0]['title']);
        static::assertSame('Cannot decrypt the refresh token', $response['errors'][0]['detail']);
    }

    public function testRevokedRefreshToken(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client);
        static::assertNotFalse($client->getResponse()->getContent());

        $oldRefreshToken = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['refresh_token'];

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $oldRefreshToken,
        ];

        // old refresh token should be invalidated here, as we issue a new refresh token with it
        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));

        // try to request a new token with the old refresh token again
        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $response = \json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $client->getResponse()->getStatusCode(),
            print_r($client->getResponse()->getContent(), true)
        );

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);
        static::assertSame('The refresh token is invalid.', $response['errors'][0]['title']);
        static::assertSame('Token has been revoked', $response['errors'][0]['detail']);
    }

    public function testRefreshToken(): void
    {
        $client = $this->getBrowser(false);

        $username = Uuid::randomHex();

        static::getContainer()->get(Connection::class)->insert('user', [
            'id' => Uuid::randomBytes(),
            'name' => $username,
            'email' => 'test@example.com',
            'username' => $username,
            'password' => TestDefaults::HASHED_PASSWORD,
            'locale_id' => Uuid::fromHexToBytes($this->getLocaleIdOfSystemLanguage()),
            'active' => 1,
            'admin' => 1,
            'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->apiUsernames[] = $username;

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $username,
            'password' => 'contenaAdmin',
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($data);
        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
        static::assertArrayHasKey(
            'refresh_token',
            $data,
            'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );

        /**
         * Issue new token with the refresh_token
         */
        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());
        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
        static::assertArrayHasKey(
            'refresh_token',
            $data,
            'No refresh_token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );

        $accessToken = $data['access_token'];
        static::assertIsString($accessToken);
        /*
         * Try access with new token
         */
        $client->setServerParameter('HTTP_Authorization', \sprintf('Bearer %s', $accessToken));
        $client->request('GET', '/api/category');

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        static::assertSame(
            Response::HTTP_OK,
            $this->getBrowser()->getResponse()->getStatusCode(),
            $this->getBrowser()->getResponse()->getContent()
        );

        static::assertNotFalse($this->getBrowser()->getResponse()->getContent());

        $response = \json_decode($this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $response);
    }

    public function testDefaultAccessTokenScopes(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client);
        $configuration = static::getContainer()->get('contena.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedAccessToken);
        $accessTokenScopes = $parsedAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing(['admin', 'write'], array_values($accessTokenScopes));
    }

    public function testUniqueAccessTokenScopes(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client, ['admin', 'write', 'admin', 'admin', 'write', 'write', 'admin']);
        $configuration = static::getContainer()->get('contena.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedAccessToken);
        $accessTokenScopes = $parsedAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing(['admin', 'write'], array_values($accessTokenScopes));
    }

    public function testAccessTokenScopesChangedAfterRefreshGrant(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client, ['admin', 'write']);
        $configuration = static::getContainer()->get('contena.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
            'scope' => 'admin', // change the scope to something different
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedAccessToken);
        $scopes = $parsedAccessToken->claims()->get('scopes');

        static::assertSame(['admin'], $scopes);
    }

    public function testSuperAdminScopeRemovedOnRefreshToken(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client, ['admin', 'write', UserVerifiedScope::IDENTIFIER]);
        $configuration = static::getContainer()->get('contena.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedOldAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedOldAccessToken);
        $oldAccessTokenScopes = $parsedOldAccessToken->claims()->get('scopes');

        static::assertContains(UserVerifiedScope::IDENTIFIER, $oldAccessTokenScopes);

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedNewAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedNewAccessToken);
        $newAccessTokenScopes = $parsedNewAccessToken->claims()->get('scopes');

        static::assertNotContains(UserVerifiedScope::IDENTIFIER, $newAccessTokenScopes);
    }

    public function testAccessTokenScopesUnchangedAfterRefreshGrant(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowser($client, ['admin', 'write']);
        $configuration = static::getContainer()->get('contena.jwt_config');
        $jwtTokenParser = $configuration->parser();

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedOldAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedOldAccessToken);
        $oldAccessTokenScopes = $parsedOldAccessToken->claims()->get('scopes');

        $refreshPayload = [
            'grant_type' => 'refresh_token',
            'client_id' => 'administration',
            'refresh_token' => $data['refresh_token'],
        ];

        $client->request('POST', '/api/oauth/token', $refreshPayload, [], [], json_encode($refreshPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($client->getResponse()->getContent());

        $content = $client->getResponse()->getContent();
        static::assertNotFalse($content);
        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $parsedNewAccessToken = $jwtTokenParser->parse($data['access_token']);
        static::assertInstanceOf(UnencryptedToken::class, $parsedNewAccessToken);
        $newAccessTokenScopes = $parsedNewAccessToken->claims()->get('scopes');

        static::assertEqualsCanonicalizing(array_values($oldAccessTokenScopes), array_values($newAccessTokenScopes));
    }

    public function testIntegrationAuth(): void
    {
        $client = $this->getBrowser(false);
        $this->authorizeBrowserWithIntegration($client);
        $client->request('GET', '/api/category');

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testIntegrationAuthInvalid(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testIntegrationAuthInvalidIdentifier(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testUserWithInvalidIdentifier(): void
    {
        $client = $this->getBrowser(false);

        $accessKey = AccessKeyHelper::generateAccessKey('user');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));

        static::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testUserWithLogin(): void
    {
        $client = $this->getBrowser(false);

        $user = TestUser::createNewTestUser(static::getContainer()->get(Connection::class));

        $accessKey = AccessKeyHelper::generateAccessKey('user');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();

        $data = [
            'userId' => $user->getUserId(),
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
        ];

        static::getContainer()->get('user_access_key.repository')
            ->create([$data], Context::createDefaultContext());

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'client_credentials',
            'client_id' => $accessKey,
            'client_secret' => $secretKey,
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));
        $response = $client->getResponse();
        $content = $response->getContent();
        static::assertNotFalse($content);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $data = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey(
            'access_token',
            $data,
            'No token returned from API: ' . ($data['errors'][0]['detail'] ?? 'unknown error')
        );
    }

    public function testInvalidGrant(): void
    {
        $client = $this->getBrowser(false);

        /**
         * Auth the api client first
         */
        $authPayload = [
            'grant_type' => 'foo',
        ];

        $client->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    public function testUnauthorizedWithPasswordGrantTypeWhenTokenExpired(): void
    {
        $browser = $this->getBrowser();

        $connection = $browser->getContainer()->get(Connection::class);
        $admin = TestUser::createNewTestUser($connection, ['category:read']);

        $authPayload = [
            'grant_type' => 'password',
            'client_id' => 'administration',
            'username' => $admin->getName(),
            'password' => $admin->getPassword(),
        ];

        $browser->request('POST', '/api/oauth/token', $authPayload, [], [], json_encode($authPayload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($browser->getResponse()->getContent());

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $token = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsString($accessToken = $token['access_token']);

        $userRepository = static::getContainer()->get('user.repository');

        // Change user password
        $userRepository->update([[
            'id' => $admin->getUserId(),
            'password' => Uuid::randomHex(),
        ]], Context::createDefaultContext());

        $browser->setServerParameter('HTTP_Authorization', \sprintf('Bearer %s', $accessToken));
        $browser->request('GET', '/api/category');
        static::assertNotFalse($browser->getResponse()->getContent());

        static::assertSame(Response::HTTP_UNAUTHORIZED, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }
}
