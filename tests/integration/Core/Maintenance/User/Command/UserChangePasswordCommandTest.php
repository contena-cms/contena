<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\User\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\User\Command\UserChangePasswordCommand;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\System\User\UserCollection;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class UserChangePasswordCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testChangesTenantUserPassword(): void
    {
        $tenantContext = $this->createTenantContext($this->createTenant('Password tenant'));
        $userId = Uuid::randomHex();
        $username = 'tenant-user-' . $userId;

        /** @var EntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = static::getContainer()->get('locale.repository');
        $localeId = $localeRepository->searchIds(new Criteria(), $tenantContext)->firstId();
        \assert($localeId !== null);

        /** @var EntityRepository<UserCollection> $userRepository */
        $userRepository = static::getContainer()->get('user.repository');
        $userRepository->create([[
            'id' => $userId,
            'username' => $username,
            'name' => 'Tenant user',
            'localeId' => $localeId,
            'email' => $userId . '@example.com',
            'password' => 'old-password',
        ]], $tenantContext);

        $newPassword = 'new-password-' . $userId;
        $command = static::getContainer()->get(UserChangePasswordCommand::class);
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'username' => $username,
            '--password' => $newPassword,
        ]);

        $commandTester->assertCommandIsSuccessful();

        $connection = static::getContainer()->get(Connection::class);
        $passwordHash = $connection->fetchOne(
            'SELECT `password` FROM `user` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($userId)],
        );

        static::assertIsString($passwordHash);
        static::assertTrue(password_verify($newPassword, $passwordHash));
    }
}
