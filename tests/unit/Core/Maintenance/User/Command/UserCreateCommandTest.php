<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\User\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Maintenance\MaintenanceException;
use Contena\Core\Maintenance\User\Command\UserCreateCommand;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UserCreateCommand::class)]
class UserCreateCommandTest extends TestCase
{
    private const string TEST_USERNAME = 'contena';

    public function testEmptyPasswordOption(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            'username' => self::TEST_USERNAME,
        ]);
        $output = $commandTester->getDisplay();
        static::assertStringContainsString('[WARNING] You didn\'t pass a password so a random one was generated.', $output);
        static::assertStringContainsString('[OK] User "contena" successfully created. The newly generated password is:', $output);
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

    public function testRoleOptionProvisionsNonSuperAdministrator(): void
    {
        $provisioner = $this->createMock(UserProvisioner::class);
        $provisioner->expects($this->once())
            ->method('provision')
            ->with(
                self::TEST_USERNAME,
                'contenaAdmin',
                [
                    'roleCode' => 'administrator',
                    'admin' => false,
                ]
            )
            ->willReturn('contenaAdmin');

        $commandTester = new CommandTester(new UserCreateCommand($provisioner));
        $commandTester->execute([
            'username' => self::TEST_USERNAME,
            '--password' => 'contenaAdmin',
            '--role' => 'administrator',
        ]);

        static::assertStringContainsString('User "contena" successfully created.', $commandTester->getDisplay());
    }

    private function getCommandTester(): CommandTester
    {
        $generator = static::createStub(AbstractNumberRangeValueGenerator::class);
        $generator->method('getValue')->willReturn('10000');

        return new CommandTester(new UserCreateCommand(new UserProvisioner(
            $this->createConnection(),
            new NativeClock(),
            $generator,
        )));
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
