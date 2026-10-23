<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelListCommand;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
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

        $channelRepository = new StaticEntityRepository([new ChannelCollection([$channel])], new ChannelDefinition());

        $command = new ChannelListCommand($channelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:list\" returned errors:\n" . $commandTester->getDisplay()
        );
        $output = '+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
| id                               | Name | Active | Maintenance | Default Language | Languages | Default Currency | Currencies | Domains |
+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
| %s | n/a  | active | off         | n/a              |           | n/a              |            |         |
+----------------------------------+------+--------+-------------+------------------+-----------+------------------+------------+---------+
';
        static::assertSame(\sprintf($output, $id), $commandTester->getDisplay());
    }

    public function testFormatJsonOutput(): void
    {
        $id = Uuid::randomHex();

        $channel = new ChannelEntity();
        $channel->setUniqueIdentifier($id);
        $channel->setId($id);
        $channel->setActive(true);
        $channel->setMaintenance(false);

        $channelRepository = new StaticEntityRepository([new ChannelCollection([$channel])], new ChannelDefinition());

        $command = new ChannelListCommand($channelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertJson($commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with `--output` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOutputJsonDeprecated(): void
    {
        $id = Uuid::randomHex();

        $channel = new ChannelEntity();
        $channel->setUniqueIdentifier($id);
        $channel->setId($id);
        $channel->setActive(true);
        $channel->setMaintenance(false);

        $channelRepository = new StaticEntityRepository([new ChannelCollection([$channel])], new ChannelDefinition());

        $command = new ChannelListCommand($channelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--output' => 'json']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertJson($commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
    }

    public function testInvalidFormatReturnsError(): void
    {
        $channelRepository = new StaticEntityRepository([new ChannelCollection([])], new ChannelDefinition());

        $command = new ChannelListCommand($channelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'xml']);

        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }
}
