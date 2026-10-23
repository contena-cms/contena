<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\AdminUser\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
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
        $adminUserRepo = StaticEntityRepository::of(AdminUserCollection::class, [[]]);
        $command = new AdminUserChangePasswordCommand($adminUserRepo);

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
        $adminUserId = Uuid::randomHex();
        $newPassword = Uuid::randomHex();

        $adminUserRepo = StaticEntityRepository::of(AdminUserCollection::class, [[$adminUserId]]);
        $command = new AdminUserChangePasswordCommand($adminUserRepo);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => $newPassword,
        ]);

        $expected = 'The password of admin_user "' . self::TEST_USERNAME . '" has been changed successfully.';
        static::assertStringContainsString($expected, $commandTester->getDisplay());
        static::assertSame(0, $commandTester->getStatusCode());

        $updates = $adminUserRepo->updates;
        $updatedData = $updates[0][0];
        static::assertSame($adminUserId, $updatedData['id']);
        static::assertSame($newPassword, $updatedData['password']);
    }

    public function testEmptyPasswordOption(): void
    {
        $adminUserRepo = static::createStub(EntityRepository::class);
        $command = new AdminUserChangePasswordCommand($adminUserRepo);

        $commandTester = new CommandTester($command);

        $violations = Validation::createValidator()->validate('', new NotBlank());
        $this->expectExceptionObject(new ValidationFailedException('', $violations));

        $commandTester->setInputs(['', '', '']);
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
    }
}
