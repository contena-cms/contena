<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\AdminUser\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\Role\AclRoleCollection;
use Contena\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserListCommand;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(AdminUserListCommand::class)]
class AdminUserListCommandTest extends TestCase
{
    public function testWithNoUsers(): void
    {
        $repo = new StaticEntityRepository([new AdminUserCollection()]);

        $command = new AdminUserListCommand($repo);
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
        $admin_userName = 'guy';
        $admin_userId = Uuid::randomHex();
        $repo = new StaticEntityRepository([
            new AdminUserCollection([
                $this->createUser('guy@contena.cn', $admin_userName, 'Guy', 'Marbello', id: $admin_userId),
            ]),
        ]);

        $command = new AdminUserListCommand($repo);
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MaintenanceException::aclRolesNotLoaded($admin_userId, $admin_userName));
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
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with `--json` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testWithJson(): void
    {
        $commandTester = $this->prepareCommandTester();
        $commandTester->execute(['--json' => true]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();

        static::assertTrue(json_validate($output));
        static::assertStringContainsString('Guy Marbello', $output);
        static::assertStringContainsString('Jen Dalimil', $output);
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
        $repo = new StaticEntityRepository([
            new AdminUserCollection([
                $this->createUser('guy@contena.cn', 'guy', 'Guy', 'Marbello', true),
                $this->createUser('jen@contena.cn', 'jen', 'Jen', 'Dalimil', false, ['Moderator', 'CS']),
            ]),
        ]);

        $command = new AdminUserListCommand($repo);

        return new CommandTester($command);
    }

    /**
     * @param array<string> $roles
     */
    private function createUser(
        string $email,
        string $username,
        string $firstName,
        string $secondName,
        bool $isAdmin = false,
        ?array $roles = null,
        ?string $id = null,
    ): AdminUserEntity {
        $admin_user = new AdminUserEntity();
        $admin_user->setId($id ?? Uuid::randomHex());
        $admin_user->setEmail($email);
        $admin_user->setActive(true);
        $admin_user->setUsername($username);
        $admin_user->setName($firstName);
        $admin_user->setLastName($secondName);
        $admin_user->setAdmin($isAdmin);
        $admin_user->setCreatedAt(new \DateTime());

        if ($roles) {
            $admin_user->setAclRoles(new AclRoleCollection(array_map(static function (string $role): AclRoleEntity {
                $aclRole = new AclRoleEntity();
                $aclRole->setId(Uuid::randomHex());
                $aclRole->setName($role);

                return $aclRole;
            }, $roles)));
        }

        return $admin_user;
    }
}
