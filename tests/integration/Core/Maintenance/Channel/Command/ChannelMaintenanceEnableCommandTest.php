<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceEnableCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ChannelMaintenanceEnableCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAllChannelIds(): void
    {
        $channelRepository = static::getContainer()->get('channel.repository');
        $count = $channelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal();

        $commandTester = new CommandTester(static::getContainer()->get(ChannelMaintenanceEnableCommand::class));
        $commandTester->execute(['--all' => true]);

        static::assertSame(
            \sprintf('Updated maintenance mode for %d sales channel(s)', $count),
            $commandTester->getDisplay()
        );
    }
}
