<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\AdminUser\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserChangePasswordCommand;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(AdminUserChangePasswordCommand::class)]
class AdminUserChangePasswordCommandTest extends TestCase
{
    private const TEST_USERNAME = 'contena';
    private const TEST_PASSWORD = 'contenaPassword';

    public function testUnknownUser(): void
    {
        $admin_userRepo = StaticEntityRepository::of(AdminUserCollection::class, [[]]);
        $command = new AdminUserChangePasswordCommand($admin_userRepo);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => self::TEST_PASSWORD,
        ]);

        $expected = 'The admin_user "' . self::TEST_USERNAME . '" does not exist.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertSame(1, $commandTester->getStatusCode());
    }

    public function testKnownUser(): void
    {
        $admin_userId = Uuid::randomHex();
        $newPassword = Uuid::randomHex();

        $admin_userRepo = StaticEntityRepository::of(AdminUserCollection::class, [[$admin_userId]]);
        $command = new AdminUserChangePasswordCommand($admin_userRepo);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => $newPassword,
        ]);

        $expected = 'The password of admin_user "' . self::TEST_USERNAME . '" has been changed successfully.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertSame(0, $commandTester->getStatusCode());

        $updates = $admin_userRepo->updates;
        $updatedData = $updates[0][0];
        static::assertSame($admin_userId, $updatedData['id']);
        static::assertSame($newPassword, $updatedData['password']);
    }

    public function testEmptyPasswordOption(): void
    {
        $admin_userRepo = static::createStub(EntityRepository::class);
        $command = new AdminUserChangePasswordCommand($admin_userRepo);

        $commandTester = new CommandTester($command);

        $violations = Validation::createValidator()->validate('', new NotBlank());
        $this->expectExceptionObject(new ValidationFailedException('', $violations));

        $commandTester->setInputs(['', '', '']);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
    }
}
