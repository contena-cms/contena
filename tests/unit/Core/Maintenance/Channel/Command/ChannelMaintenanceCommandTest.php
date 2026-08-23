<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceDisableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceEnableCommand;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelMaintenanceEnableCommand::class)]
#[CoversClass(ChannelMaintenanceDisableCommand::class)]
class ChannelMaintenanceCommandTest extends TestCase
{
    public function testEnableUpdatesSelectedChannels(): void
    {
        $repository = StaticEntityRepository::of(ChannelCollection::class, [['channel-1']], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        $commandTester->execute(['ids' => ['channel-1']]);

        static::assertSame('Updated maintenance mode for 1 channel(s)', $commandTester->getDisplay());
        static::assertSame([['id' => 'channel-1', 'maintenance' => true]], $repository->updates[0]);
    }

    public function testDisableUpdatesAllChannels(): void
    {
        $repository = StaticEntityRepository::of(ChannelCollection::class, [['channel-1', 'channel-2']], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelMaintenanceDisableCommand($repository));

        $commandTester->execute(['--all' => true]);

        static::assertSame('Updated maintenance mode for 2 channel(s)', $commandTester->getDisplay());
        static::assertSame([
            ['id' => 'channel-1', 'maintenance' => false],
            ['id' => 'channel-2', 'maintenance' => false],
        ], $repository->updates[0]);
    }

    public function testNoIdsDoesNotWrite(): void
    {
        $repository = StaticEntityRepository::of(ChannelCollection::class, [], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        $commandTester->execute([]);

        static::assertSame('No channels were updated. Provide id(s) or run with --all option.', $commandTester->getDisplay());
        static::assertSame([], $repository->updates);
    }
}
