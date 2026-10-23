<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\AdminUser\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserCreateCommand;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(AdminUserCreateCommand::class)]
class AdminUserCreateCommandTest extends TestCase
{
    private const TEST_USERNAME = 'contena';

    public function testEmptyPasswordOption(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('[WARNING] You didn\'t pass a password so a random one was generated.', $output);
        static::assertStringContainsString('[OK] AdminUser "contena" successfully created. The newly generated password is:', $output);
    }

    public function testPasswordMinLength(): void
    {
        $commandTester = $this->getCommandTester();

        $this->expectExceptionObject(MaintenanceException::passwordTooShort(8));

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => 'short',
        ]);
    }

    private function getCommandTester(): CommandTester
    {
        return new CommandTester(new AdminUserCreateCommand(new AdminUserProvisioner($this->createConnection(), new NativeClock())));
    }

    private function createConnection(): Connection
    {
        $connection = static::createStub(Connection::class);
        $builder = static::createStub(QueryBuilder::class);
        $builder->method('select')->willReturnSelf();
        $builder->method('from')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('innerJoin')->willReturnSelf();
        $builder->method('setParameter')->willReturnSelf();
        $connection->method('createQueryBuilder')->willReturn($builder);

        $connection->method('fetchOne')->willReturn('{"_value": 8}');

        return $connection;
    }
}
