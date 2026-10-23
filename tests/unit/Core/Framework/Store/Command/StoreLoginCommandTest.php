<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Command;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Command\StoreLoginCommand;
use Contena\Core\Framework\Store\Services\StoreClient;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(StoreLoginCommand::class)]
class StoreLoginCommandTest extends TestCase
{
    #[TestDox('A successful login stores the license host and reports success')]
    public function testLoginSucceeds(): void
    {
        $admin_userId = Uuid::randomHex();

        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('loginWithContenaId')
            ->willReturnCallback(function (string $contenaId, string $password, Context $context) use ($admin_userId): void {
                $this->assertSame('admin_user@example.com', $contenaId);
                $this->assertSame('secret', $password);
                $source = $context->getSource();
                $this->assertInstanceOf(AdminApiSource::class, $source);
                $this->assertSame($admin_userId, $source->getUserId());
            });

        $configService = $this->createMock(SystemConfigService::class);
        $configService
            ->expects($this->once())
            ->method('set')
            ->with('core.store.licenseHost', 'example.contena.store', null, false);

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([$admin_userId]),
            $configService
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([
            '--contenaId' => 'admin_user@example.com',
            '--password' => 'secret',
            '--admin_user' => 'admin',
            '--host' => 'example.contena.store',
        ]));
        static::assertStringContainsString('Successfully logged in.', $commandTester->getDisplay());
    }

    #[TestDox('The login fails when the admin_user is unknown')]
    public function testLoginFailsForUnknownUser(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->never())->method('loginWithContenaId');

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--contenaId' => 'admin_user@example.com',
            '--password' => 'secret',
            '--admin_user' => 'unknown',
        ]));
        static::assertStringContainsString('AdminUser not found', $commandTester->getDisplay());
    }

    #[TestDox('The login fails without a Contena ID')]
    public function testLoginFailsWithoutContenaId(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient->expects($this->never())->method('loginWithContenaId');

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([Uuid::randomHex()]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--password' => 'secret',
            '--admin_user' => 'admin',
        ]));
        static::assertStringContainsString('Contena ID and password are required.', $commandTester->getDisplay());
    }

    #[TestDox('A rejected store login is reported as failure')]
    public function testLoginFailsWhenStoreRejectsCredentials(): void
    {
        $storeClient = $this->createMock(StoreClient::class);
        $storeClient
            ->expects($this->once())
            ->method('loginWithContenaId')
            ->willThrowException(new ClientException(
                'Invalid credentials',
                new Request('POST', '/swplatform/login'),
                new Response(401)
            ));

        $commandTester = new CommandTester(new StoreLoginCommand(
            $storeClient,
            $this->createUserRepository([Uuid::randomHex()]),
            static::createStub(SystemConfigService::class)
        ));

        static::assertSame(Command::FAILURE, $commandTester->execute([
            '--contenaId' => 'admin_user@example.com',
            '--password' => 'wrong',
            '--admin_user' => 'admin',
        ]));
        static::assertStringContainsString('Store login failed: Invalid credentials', $commandTester->getDisplay());
    }

    /**
     * @param list<string> $admin_userIds
     *
     * @return StaticEntityRepository<AdminUserCollection>
     */
    private function createUserRepository(array $admin_userIds): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(AdminUserCollection::class, [$admin_userIds]);

        return $repository;
    }
}
