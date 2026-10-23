<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service\Requirement;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Service\Requirement\ContenaAccountRequirement;
use Contena\Core\Service\Requirement\Gate;

/**
 * @internal
 */
#[CoversClass(ContenaAccountRequirement::class)]
class ContenaAccountRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('contena_account', ContenaAccountRequirement::getName());
    }

    public function testGatesPrivileges(): void
    {
        static::assertSame(Gate::PRIVILEGES, (new ContenaAccountRequirement(static::createStub(Connection::class)))->getGate());
    }

    public function testDispermitsStateChange(): void
    {
        static::assertFalse((new ContenaAccountRequirement(static::createStub(Connection::class)))->permitsStateChange());
    }

    public function testIsSatisfiedWhenUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `admin_user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn('1');

        $requirement = new ContenaAccountRequirement($connection);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenNoUserHasStoreToken(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT 1 FROM `admin_user` WHERE `store_token` IS NOT NULL LIMIT 1')
            ->willReturn(false);

        $requirement = new ContenaAccountRequirement($connection);

        static::assertFalse($requirement->isSatisfied());
    }
}
