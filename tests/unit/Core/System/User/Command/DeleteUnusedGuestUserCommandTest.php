<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Command\DeleteUnusedGuestUsersCommand;
use Contena\Core\System\User\DeleteUnusedGuestUserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DeleteUnusedGuestUsersCommand::class)]
class DeleteUnusedGuestUsersCommandTest extends TestCase
{
    private MockObject&DeleteUnusedGuestUserService $service;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->service = $this->createMock(DeleteUnusedGuestUserService::class);

        $this->commandTester = new CommandTester(new DeleteUnusedGuestUsersCommand($this->service));
    }

    #[TestDox('Nothing is deleted when no unused guest users exist')]
    public function testSucceedsEarlyWithoutUnusedGuestUsers(): void
    {
        $this->service->method('countUnusedUsers')->willReturn(0);
        $this->service->expects($this->never())->method('deleteUnusedUsers');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('No unused guest users found.', $this->commandTester->getDisplay());
    }

    #[TestDox('Nothing is deleted when the confirmation is declined')]
    public function testAbortsWithoutConfirmation(): void
    {
        $this->service->method('countUnusedUsers')->willReturn(3);
        $this->service->expects($this->never())->method('deleteUnusedUsers');

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([], ['interactive' => false]));
        static::assertStringContainsString('Aborting due to admin_user input.', $this->commandTester->getDisplay());
    }

    #[TestDox('Unused guest users are deleted in batches after confirmation')]
    public function testDeletesUnusedGuestUsersAfterConfirmation(): void
    {
        $this->service->method('countUnusedUsers')->willReturn(2);
        $this->service
            ->expects($this->exactly(2))
            ->method('deleteUnusedUsers')
            ->willReturnOnConsecutiveCalls(
                [Uuid::randomHex(), Uuid::randomHex()],
                []
            );

        $this->commandTester->setInputs(['yes']);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute([]));
        static::assertStringContainsString('Successfully deleted 2 guest users.', $this->commandTester->getDisplay());
    }
}
