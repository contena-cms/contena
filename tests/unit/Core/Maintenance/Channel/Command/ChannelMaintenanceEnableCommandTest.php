<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceEnableCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelMaintenanceEnableCommand::class)]
class ChannelMaintenanceEnableCommandTest extends TestCase
{
    public function testNoIdsReturnsWithoutUpdating(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('searchIds');
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertSame(
            'No channels were updated. Provide id(s) or run with --all option.',
            $tester->getDisplay(),
        );
    }

    public function testIdsUpdateMaintenanceMode(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getIds() === ['channel-id'];
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn(IdSearchResult::fromIds(['channel-id'], new Criteria(), Context::createCLIContext()));
        $repository->expects($this->once())
            ->method('update')
            ->with(
                [['id' => 'channel-id', 'maintenance' => true]],
                static::isInstanceOf(Context::class),
            );

        $tester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['channel-id']]));
        static::assertSame('Updated maintenance mode for 1 channel(s)', $tester->getDisplay());
    }

    public function testAllIdsUpdateAllChannels(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('searchIds')
            ->with(
                static::callback(static function (Criteria $criteria): bool {
                    return $criteria->getIds() === [];
                }),
                static::isInstanceOf(Context::class),
            )
            ->willReturn(IdSearchResult::fromIds(['first-id', 'second-id'], new Criteria(), Context::createCLIContext()));
        $repository->expects($this->once())
            ->method('update')
            ->with(
                [
                    ['id' => 'first-id', 'maintenance' => true],
                    ['id' => 'second-id', 'maintenance' => true],
                ],
                static::isInstanceOf(Context::class),
            );

        $tester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('Updated maintenance mode for 2 channel(s)', $tester->getDisplay());
    }

    public function testNoMatchingChannelsReturnsWithoutUpdating(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('searchIds')->willReturn(
            IdSearchResult::fromIds([], new Criteria(), Context::createCLIContext()),
        );
        $repository->expects($this->never())->method('update');

        $tester = new CommandTester(new ChannelMaintenanceEnableCommand($repository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['unknown-id']]));
        static::assertSame('No channels were updated', $tester->getDisplay());
    }
}
