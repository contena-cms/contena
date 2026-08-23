<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\Maintenance\User\Command\UserListCommand;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UserListCommand::class)]
class UserListCommandTest extends TestCase
{
    public function testWithNoUsers(): void
    {
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([new UserCollection()]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('There are no users', $output);
    }

    public function testWithUsers(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
    }

    public function testAclRolesNotLoadedException(): void
    {
        $userName = 'guy';
        $userId = Uuid::randomHex();
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@contena.cn', $userName, 'Guy Marbello', id: $userId),
            ]),
        ]);

        $command = new UserListCommand($repo);
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MaintenanceException::aclRolesNotLoaded($userId, $userName));
        $commandTester->execute([]);
    }

    public function testWithFormatJson(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--format' => 'json']);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);

        /** @var list<array{active: bool}> $users */
        $users = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($users[0]['active']);
        static::assertFalse($users[1]['active']);
    }

    public function testInvalidFormatReturnsError(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--format' => 'xml']);

        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }

    private function prepareCommandTester(): CommandTester
    {
        /** @var StaticEntityRepository<UserCollection> $repo */
        $repo = new StaticEntityRepository([
            new UserCollection([
                $this->createUser('guy@contena.cn', 'guy', 'Guy Marbello', true),
                $this->createUser('jen@contena.cn', 'jen', 'Jen Dalimil', false, ['Moderator', 'CS']),
            ]),
        ]);

        $command = new UserListCommand($repo);

        return new CommandTester($command);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $email,
        string $username,
        string $name,
        bool $isAdmin = false,
        ?array $roles = null,
        ?string $id = null,
    ): UserEntity {
        $user = new UserEntity();
        $user->setId($id ?? Uuid::randomHex());
        $user->setEmail($email);
        $user->setActive($isAdmin);
        $user->setUsername($username);
        $user->setName($name);
        $user->setAdmin($isAdmin);
        $user->setCreatedAt(new \DateTime());

        if ($roles) {
            $user->setAclRoles(new AclRoleCollection(array_map(static function (string $role): AclRoleEntity {
                $aclRole = new AclRoleEntity();
                $aclRole->setId(Uuid::randomHex());
                $aclRole->setName($role);

                return $aclRole;
            }, $roles)));
        }

        return $user;
    }
}
