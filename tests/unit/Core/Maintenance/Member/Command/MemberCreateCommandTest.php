<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Member\Command;

use Contena\Core\Maintenance\Member\Command\MemberCreateCommand;
use Contena\Core\Maintenance\Member\Service\MemberProvisioner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(MemberCreateCommand::class)]
class MemberCreateCommandTest extends TestCase
{
    public function testCreatesMemberWithProvidedProfile(): void
    {
        $provisioner = $this->createMock(MemberProvisioner::class);
        $provisioner->expects($this->once())
            ->method('provision')
            ->with('admin@example.com', 'contenaAdmin', 'Administrator')
            ->willReturn('contenaAdmin');

        $commandTester = new CommandTester(new MemberCreateCommand($provisioner));
        $commandTester->execute([
            'email' => 'admin@example.com',
            '--password' => 'contenaAdmin',
            '--name' => 'Administrator',
        ]);

        static::assertStringContainsString('Member "admin@example.com" successfully created.', $commandTester->getDisplay());
    }

    public function testReportsGeneratedPasswordWhenNoneWasProvided(): void
    {
        $provisioner = $this->createMock(MemberProvisioner::class);
        $provisioner->expects($this->once())
            ->method('provision')
            ->with('admin@example.com', null, null)
            ->willReturn('generated-password');

        $commandTester = new CommandTester(new MemberCreateCommand($provisioner));
        $commandTester->execute(['email' => 'admin@example.com']);

        static::assertStringContainsString('random one was generated', $commandTester->getDisplay());
        static::assertStringContainsString('The newly generated', $commandTester->getDisplay());
        static::assertStringContainsString('password is: generated-password', $commandTester->getDisplay());
    }
}
