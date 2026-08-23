<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelListCommand;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelListCommand::class)]
class ChannelListCommandTest extends TestCase
{
    public function testNoValidationErrors(): void
    {
        $id = Uuid::randomHex();
        $channel = new ChannelEntity();
        $channel->setUniqueIdentifier($id);
        $channel->setId($id);
        $channel->setActive(true);
        $channel->setMaintenance(false);

        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [new ChannelCollection([$channel])], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelListCommand($channelRepository));
        $commandTester->execute([]);

        static::assertSame(0, $commandTester->getStatusCode(), "\"bin/console channel:list\" returned errors:\n" . $commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
        static::assertStringContainsString('active', $commandTester->getDisplay());
    }

    public function testFormatJsonOutput(): void
    {
        $id = Uuid::randomHex();
        $channel = new ChannelEntity();
        $channel->setUniqueIdentifier($id);
        $channel->setId($id);
        $channel->setActive(true);
        $channel->setMaintenance(false);

        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [new ChannelCollection([$channel])], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelListCommand($channelRepository));
        $commandTester->execute(['--format' => 'json']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertJson($commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
    }

    public function testInvalidFormatReturnsError(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [new ChannelCollection([])], new ChannelDefinition());
        $commandTester = new CommandTester(new ChannelListCommand($channelRepository));
        $commandTester->execute(['--format' => 'xml']);

        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }
}
