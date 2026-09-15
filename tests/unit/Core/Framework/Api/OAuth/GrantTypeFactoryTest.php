<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\OAuth;

use Contena\Core\Framework\Api\OAuth\ContenaAuthCodeGrantType;
use Contena\Core\Framework\Api\OAuth\GrantTypeFactory;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrantTypeFactory::class)]
class GrantTypeFactoryTest extends TestCase
{
    public function testCreatesAllGrantTypesOfTheAdminApi(): void
    {
        $factory = new GrantTypeFactory(
            static::createStub(UserRepositoryInterface::class),
            static::createStub(RefreshTokenRepository::class),
            static::createStub(AuthCodeRepositoryInterface::class),
        );

        $grantTypes = $factory->createGrantTypes();
        $identifiers = array_map(static fn ($grantType) => $grantType->getIdentifier(), $grantTypes);

        static::assertSame(['password', 'refresh_token', 'client_credentials', 'authorization_code'], $identifiers);
        static::assertInstanceOf(PasswordGrant::class, $grantTypes[0]);
        static::assertInstanceOf(RefreshTokenGrant::class, $grantTypes[1]);
        static::assertInstanceOf(ClientCredentialsGrant::class, $grantTypes[2]);
        static::assertInstanceOf(ContenaAuthCodeGrantType::class, $grantTypes[3]);
    }
}
