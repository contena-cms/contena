<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Elasticsearch\Admin\AdminIndexingBehavior;
use Contena\Elasticsearch\Admin\AdminSearchRegistry;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ElasticsearchAdminIndexingCommand::class)]
class ElasticsearchAdminIndexingCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);

        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(true, [], ['promotion']));
        $commandTester = new CommandTester(new ElasticsearchAdminIndexingCommand($registry));
        $commandTester->execute(['--no-queue' => true, '--only' => 'promotion']);

        $commandTester->assertCommandIsSuccessful();
    }
}
