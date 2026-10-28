<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Maintenance\SalesChannel\Command\SalesChannelMaintenanceEnableCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelMaintenanceEnableCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAllSalesChannelIds(): void
    {
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        $count = $salesChannelRepository->search(new Criteria(), Context::createDefaultContext())->getTotal();

        $commandTester = new CommandTester(static::getContainer()->get(SalesChannelMaintenanceEnableCommand::class));
        $commandTester->execute(['--all' => true]);

        static::assertSame(
            \sprintf('Updated maintenance mode for %d sales channel(s)', $count),
            $commandTester->getDisplay()
        );
    }
}
