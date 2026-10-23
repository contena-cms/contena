<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceDisableCommand;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ChannelMaintenanceDisableCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNoValidationErrors(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceDisableCommand::class));
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:maintenance:disable\" returned errors:\n" . $commandTester->getDisplay()
        );
    }

    public function testUnknownChannelIds(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceDisableCommand::class));
        $commandTester->execute(['ids' => [Uuid::randomHex()]]);

        static::assertSame(
            'No sales channels were updated',
            $commandTester->getDisplay()
        );
    }

    public function testNoChannelIds(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceDisableCommand::class));
        $commandTester->execute([]);

        static::assertSame(
            'No sales channels were updated. Provide id(s) or run with --all option.',
            $commandTester->getDisplay()
        );
    }

    public function testOneChannelIds(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceDisableCommand::class));
        $commandTester->execute(['ids' => [TestDefaults::CHANNEL]]);

        static::assertSame(
            'Updated maintenance mode for 1 sales channel(s)',
            $commandTester->getDisplay()
        );
    }

    public function testAllChannelIds(): void
    {
        $channelRepository = static::getContainer()->get('channel.repository');
        $count = $channelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal();

        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceDisableCommand::class));
        $commandTester->execute(['--all' => true]);

        static::assertSame(
            \sprintf('Updated maintenance mode for %d sales channel(s)', $count),
            $commandTester->getDisplay()
        );
    }
}
