<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\OAuth\GrantTypeFactory;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\OAuth\ContenaAuthCodeGrantType;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\ContenaGrantType;
use Contena\Core\Framework\Sso\ContenaPasswordGrantType;
use Contena\Core\Framework\Sso\ContenaRefreshTokenGrantType;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\TokenService\IdTokenParser;
use Contena\Core\Framework\Sso\TokenService\PublicKeyLoader;
use Contena\Core\Framework\Sso\UserService\UserService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(GrantTypeFactory::class)]
class GrantTypeFactoryTest extends TestCase
{
    public function testCreatesAllGrantTypesOfTheAdminApi(): void
    {
        $clock = new NativeClock();
        $loginConfigService = static::createStub(LoginConfigService::class);
        $externalTokenService = new ExternalTokenService(static::createStub(HttpClientInterface::class), $loginConfigService);
        $userService = new UserService(
            static::createStub(Connection::class),
            new IdTokenParser(
                new PublicKeyLoader(static::createStub(HttpClientInterface::class), $loginConfigService, new ArrayAdapter()),
                $loginConfigService,
                $clock
            ),
            StaticEntityRepository::of(UserCollection::class, []),
            $externalTokenService,
            $clock,
        );

        $factory = new GrantTypeFactory(
            static::createStub(UserRepositoryInterface::class),
            static::createStub(RefreshTokenRepository::class),
            static::createStub(AuthCodeRepositoryInterface::class),
            $userService,
            $externalTokenService,
            $clock,
        );

        $grantTypes = $factory->createGrantTypes();

        $identifiers = array_map(static fn ($grantType) => $grantType->getIdentifier(), $grantTypes);
        static::assertSame(
            ['password', 'refresh_token', 'client_credentials', ContenaGrantType::TYPE, 'authorization_code'],
            $identifiers
        );

        static::assertInstanceOf(ContenaPasswordGrantType::class, $grantTypes[0]);
        static::assertInstanceOf(ContenaRefreshTokenGrantType::class, $grantTypes[1]);
        static::assertInstanceOf(ContenaGrantType::class, $grantTypes[3]);
        static::assertInstanceOf(ContenaAuthCodeGrantType::class, $grantTypes[4]);
    }
}
