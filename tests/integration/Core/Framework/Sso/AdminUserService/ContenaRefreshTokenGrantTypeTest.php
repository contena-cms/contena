<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso\Login;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use Nyholm\Psr7\Response as Psr7Response;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\AccessTokenRepository;
use Contena\Core\Framework\Api\OAuth\ClientRepository;
use Contena\Core\Framework\Api\OAuth\FakeCryptKey;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\OAuth\ScopeRepository;
use Contena\Core\Framework\Api\OAuth\AdminUserRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\ContenaPasswordGrantType;
use Contena\Core\Framework\Sso\ContenaRefreshTokenGrantType;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Contena\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Contena\Tests\Integration\Core\Framework\Sso\Helper\FakeTokenGenerator;
use Contena\Tests\Integration\Core\Framework\Sso\Helper\FakeUserInstaller;
use Contena\Tests\Unit\Core\Framework\Sso\TokenService\_fixtures\JwksIds;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
class ContenaRefreshTokenGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRespondToAccessTokenRequestWithoutOAuthUser(): void
    {
        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);
        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $contenaRefreshTokenGrantType = $this->createContenaRefreshTokenGrantType();

        $response = $contenaRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');
    }

    public function testRespondToAccessTokenRequestWithOAuthUser(): void
    {
        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);

        $connection = $this->getContainer()->get(Connection::class);
        $admin_userId = Uuid::fromBytesToHex(
            $connection->createQueryBuilder()
                ->select('id')
                ->from('admin_user')
                ->where('username = :username')
                ->setParameter('username', 'admin')
                ->executeQuery()
                ->fetchOne()
        );

        (new FakeUserInstaller($connection))->installTokenUser($admin_userId, Uuid::randomHex());

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $contenaRefreshTokenGrantType = $this->createContenaRefreshTokenGrantType();

        $response = $contenaRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');

        $tokenResult = $connection->createQueryBuilder()
            ->select('token')
            ->from('oauth_user')
            ->where('admin_user_id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($admin_userId))
            ->executeQuery()
            ->fetchOne();

        static::assertIsString($tokenResult);
        $jsonResult = \json_decode($tokenResult, true);
        static::assertIsArray($jsonResult);
        static::assertArrayHasKey('token', $jsonResult);
        static::assertArrayHasKey('refreshToken', $jsonResult);

        // expect updated oauth_user.token
        static::assertSame('new_access_token', $jsonResult['token']);
        static::assertSame('new_refresh_token', $jsonResult['refreshToken']);
    }

    public function testRespondToAccessTokenRequestWithOAuthUserWithNullToken(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $admin_userId = Uuid::fromBytesToHex(
            $connection->createQueryBuilder()
                ->select('id')
                ->from('admin_user')
                ->where('username = :username')
                ->setParameter('username', 'admin')
                ->executeQuery()
                ->fetchOne()
        );

        (new FakeUserInstaller($connection))->installTokenUser($admin_userId, Uuid::randomHex());

        $refreshToken = $this->createRefreshToken();
        $request = $this->createRefreshGrantRequest($refreshToken);

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');

        $contenaRefreshTokenGrantType = $this->createContenaRefreshTokenGrantType();

        $response = $contenaRefreshTokenGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');

        $tokenResult = $connection->createQueryBuilder()
            ->select('token')
            ->from('oauth_user')
            ->where('admin_user_id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($admin_userId))
            ->executeQuery()
            ->fetchOne();

        static::assertNull($tokenResult);
    }

    private function createContenaRefreshTokenGrantType(): ContenaRefreshTokenGrantType
    {
        $contenaRefreshTokenGrantType = new ContenaRefreshTokenGrantType(
            $this->getContainer()->get(RefreshTokenRepository::class),
            $this->getContainer()->get(AdminUserService::class),
            $this->createExternalTokenService(),
            new NativeClock()
        );

        $contenaRefreshTokenGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $contenaRefreshTokenGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $contenaRefreshTokenGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $contenaRefreshTokenGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $contenaRefreshTokenGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $contenaRefreshTokenGrantType->setDefaultScope('');
        $contenaRefreshTokenGrantType->setEncryptionKey('key');

        return $contenaRefreshTokenGrantType;
    }

    private function createRefreshToken(): string
    {
        $request = $this->createPasswordGrantRequest();

        $psrHttpFactory = $this->getContainer()->get(PsrHttpFactory::class);
        $psr7Request = $psrHttpFactory->createRequest($request);

        $bearerResponse = new BearerTokenResponse();
        $bearerResponse->setEncryptionKey('key');

        $ttl = new \DateInterval('PT1H');
        $contenaPasswordGrantType = $this->createContenaPasswortGrantType();

        $response = $contenaPasswordGrantType->respondToAccessTokenRequest($psr7Request, $bearerResponse, $ttl);
        $result = $response->generateHttpResponse(new Psr7Response());
        static::assertSame(Response::HTTP_OK, $result->getStatusCode());

        $responseBodyData = \json_decode($result->getBody()->__toString(), true);
        static::assertIsArray($responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);

        return $responseBodyData['refresh_token'];
    }

    private function createPasswordGrantRequest(): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', 'password');
        $request->request->set('scope', 'write');
        $request->request->set('username', 'admin');
        $request->request->set('password', 'contena');

        return $request;
    }

    private function createRefreshGrantRequest(string $refreshToken): Request
    {
        $request = new Request();
        $request->headers->set('HOST', 'foo');
        $request->headers->set('SERVER_PORT', '443');
        $request->server->set('HTTPS', 'on');
        $request->request->set('client_id', 'administration');
        $request->request->set('grant_type', 'refresh_token');
        $request->request->set('scope', 'write');
        $request->request->set('refresh_token', $refreshToken);

        return $request;
    }

    private function createContenaPasswortGrantType(): ContenaPasswordGrantType
    {
        $contenaPasswordGrantType = new ContenaPasswordGrantType(
            $this->getContainer()->get(AdminUserRepository::class),
            new RefreshTokenRepository($this->getContainer()->get(Connection::class), new NativeClock()),
            $this->getContainer()->get(AdminUserService::class)
        );

        $contenaPasswordGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $contenaPasswordGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $contenaPasswordGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $contenaPasswordGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $contenaPasswordGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $contenaPasswordGrantType->setDefaultScope('');

        return $contenaPasswordGrantType;
    }

    private function createExternalTokenService(): ExternalTokenService
    {
        $idToken = (new FakeTokenGenerator())->setEmail('admin_user@example.com')->generate(JwksIds::KEY_ID_ONE);

        $responseInterface = static::createStub(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $idToken,
                    'access_token' => 'new_access_token',
                    'refresh_token' => 'new_refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($responseInterface);

        $loginConfig = new LoginConfigService(
            [
                'use_default' => false,
                'client_id' => 'client_id',
                'client_secret' => 'client_secret',
                'redirect_uri' => 'http://redirect.uri',
                'base_url' => 'http://base.uri',
                'authorize_path' => '/authorize',
                'token_path' => '/token',
                'jwks_path' => '/jwks.json',
                'scope' => 'scope',
                'register_url' => 'https://register.url',
            ],
            static::createStub(RouterInterface::class)
        );

        return new ExternalTokenService($client, $loginConfig);
    }
}
