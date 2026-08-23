<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Tenant\Resolver;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Tenant\Resolver\SubdomainTenantResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(SubdomainTenantResolver::class)]
class SubdomainTenantResolverTest extends TestCase
{
    public function testResolvesTheTenantCodeFromTheFirstHostLabel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT LOWER(HEX(`id`)) FROM `tenant` WHERE `code` = :code AND `status` = 1', ['code' => 'ac'])
            ->willReturn('019fff00000000000000000000000000');

        $resolution = $this->resolver($connection)->resolve(Request::create('https://ac.contena.cn/'));

        static::assertNotNull($resolution);
        static::assertSame('019fff00000000000000000000000000', $resolution->tenantId);
        static::assertSame(SubdomainTenantResolver::ID, $resolution->source);
    }

    public function testIgnoresHostsWithoutASubdomainLabel(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        static::assertNull($this->resolver($connection)->resolve(Request::create('https://localhost/')));
    }

    public function testIgnoresInvalidTenantCodes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        static::assertNull($this->resolver($connection)->resolve(Request::create('https://A_C.contena.cn/')));
    }

    public function testReturnsNullForUnknownTenantCodes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn(false);

        static::assertNull($this->resolver($connection)->resolve(Request::create('https://unknown.contena.cn/')));
    }

    private function resolver(Connection $connection): SubdomainTenantResolver
    {
        return new SubdomainTenantResolver($connection, $this->validator());
    }

    private function validator(): ValidatorInterface
    {
        // Only the Regex constraint is exercised by the resolver.
        return Validation::createValidator();
    }
}
