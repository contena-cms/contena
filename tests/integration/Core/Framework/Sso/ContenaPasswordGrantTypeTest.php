<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso;

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
use Contena\Core\Framework\Api\OAuth\UserRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\ContenaPasswordGrantType;
use Contena\Core\Framework\Sso\UserService\UserService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Checkout\Payment\Cart\Token\TestKey;
use Contena\Core\Test\Stub\Checkout\Payment\Cart\Token\TestSigner;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContenaPasswordGrantTypeTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testRespondToAccessTokenWithoutOAuthUser(): void
    {
        $request = $this->createRequest();

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
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');
    }

    public function testRespondToAccessTokenWithOAuthUser(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $userId = Uuid::fromBytesToHex(
            $connection->createQueryBuilder()
                ->select('id')
                ->from('user')
                ->where('username = :username')
                ->setParameter('username', 'admin')
                ->executeQuery()
                ->fetchOne()
        );

        (new FakeUserInstaller($connection))->installTokenUser($userId, Uuid::randomHex());

        $request = $this->createRequest();

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
        static::assertArrayHasKey('token_type', $responseBodyData);
        static::assertArrayHasKey('expires_in', $responseBodyData);
        static::assertArrayHasKey('access_token', $responseBodyData);
        static::assertArrayHasKey('refresh_token', $responseBodyData);
        static::assertSame($responseBodyData['token_type'], 'Bearer');

        $tokenResult = $connection->createQueryBuilder()
            ->select('token')
            ->from('oauth_user')
            ->where('user_id = :userId')
            ->setParameter('userId', Uuid::fromHexToBytes($userId))
            ->executeQuery()
            ->fetchOne();

        static::assertNull($tokenResult);
    }

    public function createRequest(): Request
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

    private function createContenaPasswortGrantType(): ContenaPasswordGrantType
    {
        $contenaPasswordGrantType = new ContenaPasswordGrantType(
            $this->getContainer()->get(UserRepository::class),
            new RefreshTokenRepository($this->getContainer()->get(Connection::class), new NativeClock()),
            $this->getContainer()->get(UserService::class)
        );

        $contenaPasswordGrantType->setClientRepository($this->getContainer()->get(ClientRepository::class));
        $contenaPasswordGrantType->setScopeRepository($this->getContainer()->get(ScopeRepository::class));
        $contenaPasswordGrantType->setAccessTokenRepository($this->getContainer()->get(AccessTokenRepository::class));
        $contenaPasswordGrantType->setPrivateKey(new FakeCryptKey(Configuration::forSymmetricSigner(new TestSigner(), new TestKey())));
        $contenaPasswordGrantType->setRefreshTokenTTL(new \DateInterval('PT1H'));
        $contenaPasswordGrantType->setDefaultScope('');

        return $contenaPasswordGrantType;
    }
}
