<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Sso\Helper;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Validator as ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\TokenService\IdTokenParser;
use Contena\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
class ValidUserServiceCreator extends TestCase
{
    /**
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function create(Connection $connection, EntityRepository $adminUserRepository): AdminUserService
    {
        $publicKeyLoader = new PublicKeyLoader(
            $this->createClient(),
            $this->createLoginConfigService(),
            new ArrayAdapter()
        );

        $idTokenParser = new IdTokenParser(
            $publicKeyLoader,
            $this->createLoginConfigService(),
            static::createStub(ClockInterface::class)
        );

        $validator = static::createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(true);

        $validatorProperty = (new \ReflectionClass(IdTokenParser::class))->getProperty('validator');
        $validatorProperty->setValue($idTokenParser, $validator);

        return new AdminUserService($connection, $idTokenParser, $adminUserRepository, $this->createExternalTokenService(), new NativeClock());
    }

    private function createClient(): HttpClientInterface
    {
        $filesystem = new Filesystem();
        $jwks = $filesystem->readFile(__DIR__ . '/../../../../../unit/Core/Framework/Sso/TokenService/_fixtures/jwks.json');

        $response = static::createStub(ResponseInterface::class);
        $response->method('getContent')->willReturn($jwks);

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return $client;
    }

    private function createLoginConfigService(): LoginConfigService
    {
        $rawConfig = [
            'use_default' => false,
            'client_id' => 'c6a7ab8a-5c0c-4353-a38a-1b42479ef090',
            'client_secret' => '42fec3f9-a19b-4796-bce9-cb395a28da9f',
            'redirect_uri' => 'https://redirect.to',
            'base_url' => 'https://base.url',
            'authorize_path' => '/authorize',
            'token_path' => '/token',
            'jwks_path' => '/jwks.json',
            'scope' => 'scope',
            'register_url' => 'https://register.url',
        ];

        return new LoginConfigService($rawConfig, static::createStub(RouterInterface::class));
    }

    private function createExternalTokenService(): ExternalTokenService
    {
        $token = (new FakeTokenGenerator())->generate();
        $responseInterface = static::createStub(ResponseInterface::class);
        $responseInterface->method('getContent')->willReturn(
            \json_encode(
                [
                    'id_token' => $token,
                    'access_token' => 'access_token',
                    'refresh_token' => 'refresh_token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                    'scope' => 'scope',
                ]
            )
        );

        $client = static::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($responseInterface);

        $loginConfigService = $this->createLoginConfigService();

        return new ExternalTokenService($client, $loginConfigService);
    }
}
