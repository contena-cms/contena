<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Contena\Core\Framework\Adapter\Command\CacheClearHttpCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(CacheClearAllCommand::class)]
class CacheClearHttpCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $cache = $this->createMock(CacheClearer::class);
        $cache->expects($this->once())->method('clearHttpCache');

        $command = new CacheClearHttpCommand($cache);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
