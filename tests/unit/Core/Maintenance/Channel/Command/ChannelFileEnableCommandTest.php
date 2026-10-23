<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelFileEnableCommand;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelFileEnableCommand::class)]
class ChannelFileEnableCommandTest extends TestCase
{
    private const FRONTEND_ID = '0191a1b2c3d4e5f60718293a4b5c6d7e';

    private const HEADLESS_ID = '0191a1b2c3d4e5f60718293a4b5c6d7f';

    public function testWithoutIdsOrAllNothingIsEnabled(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class);
        $fileRepository = StaticEntityRepository::of(ChannelFileCollection::class);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertSame('No files were enabled. Provide id(s) or run with --all option.', $tester->getDisplay());
        static::assertSame([], $fileRepository->creates);
    }

    public function testItCreatesTheMissingFilesForTheSelectedChannels(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [[self::FRONTEND_ID, self::HEADLESS_ID]]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection()]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [self::FRONTEND_ID, self::HEADLESS_ID]]));
        static::assertSame('Enabled 4 file(s) for 2 sales channel(s)', $tester->getDisplay());
        static::assertSame([[
            ['channelId' => self::FRONTEND_ID, 'fileFamily' => 'agentic', 'fileName' => 'llms.txt', 'enabled' => true],
            ['channelId' => self::FRONTEND_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
            ['channelId' => self::HEADLESS_ID, 'fileFamily' => 'agentic', 'fileName' => 'llms.txt', 'enabled' => true],
            ['channelId' => self::HEADLESS_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
        ]], $fileRepository->creates);
    }

    public function testAFileTheMerchantSwitchedOffStaysOff(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [[self::FRONTEND_ID]]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection([
            $this->file(self::FRONTEND_ID, 'llms.txt', enabled: false),
        ])]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));
        $tester->execute(['--all' => true]);

        static::assertSame('Enabled 1 file(s) for 1 sales channel(s)', $tester->getDisplay());
        static::assertSame([[
            ['channelId' => self::FRONTEND_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
        ]], $fileRepository->creates);
        static::assertSame([], $fileRepository->updates);
    }

    public function testALowercaseAgentsMdRowCountsAsExisting(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [[self::FRONTEND_ID]]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection([
            $this->file(self::FRONTEND_ID, 'llms.txt', enabled: true),
            $this->file(self::FRONTEND_ID, 'agents.md', enabled: true),
        ])]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('No files were enabled. The files already exist for every selected sales channel.', $tester->getDisplay());
        static::assertSame([], $fileRepository->creates);
    }

    public function testOnlyFrontendAndHeadlessChannelsAreSelected(): void
    {
        $selectedTypes = null;
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [
            static function (Criteria $criteria) use (&$selectedTypes): array {
                $filter = $criteria->getFilters()[0] ?? null;
                static::assertInstanceOf(EqualsAnyFilter::class, $filter);
                $selectedTypes = $filter->getValue();

                return [];
            },
        ]);
        $fileRepository = StaticEntityRepository::of(ChannelFileCollection::class);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('No files were enabled. No Frontend or Headless sales channel matches.', $tester->getDisplay());
        static::assertSame([Defaults::CHANNEL_TYPE_FRONTEND, Defaults::CHANNEL_TYPE_API], $selectedTypes);
        static::assertSame([], $fileRepository->creates);
    }

    public function testUppercaseIdsAreSearchedInLowercase(): void
    {
        $searchedIds = null;
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [
            static function (Criteria $criteria) use (&$searchedIds): array {
                $searchedIds = $criteria->getIds();

                return [self::FRONTEND_ID];
            },
        ]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection()]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [strtoupper(self::FRONTEND_ID)]]));
        static::assertSame([self::FRONTEND_ID], $searchedIds);
        static::assertSame('Enabled 2 file(s) for 1 sales channel(s)', $tester->getDisplay());
    }

    public function testInvalidIdsAreReportedAndIgnored(): void
    {
        $searchedIds = null;
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [
            static function (Criteria $criteria) use (&$searchedIds): array {
                $searchedIds = $criteria->getIds();

                return [self::FRONTEND_ID];
            },
        ]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection()]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['foo', self::FRONTEND_ID]]));
        static::assertSame([self::FRONTEND_ID], $searchedIds);
        static::assertSame(
            'Ignored invalid id(s): foo' . \PHP_EOL . 'Enabled 2 file(s) for 1 sales channel(s)',
            $tester->getDisplay(),
        );
    }

    public function testIdsWithoutAFrontendOrHeadlessChannelAreReported(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class, [[self::FRONTEND_ID]]);
        $fileRepository = new StaticEntityRepository([new ChannelFileCollection()]);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [self::FRONTEND_ID, self::HEADLESS_ID]]));
        static::assertSame(
            'Ignored id(s) that match no Frontend or Headless sales channel: ' . self::HEADLESS_ID . \PHP_EOL
            . 'Enabled 2 file(s) for 1 sales channel(s)',
            $tester->getDisplay(),
        );
    }

    public function testOnlyInvalidIdsDoNotSelectEveryChannel(): void
    {
        $channelRepository = StaticEntityRepository::of(ChannelCollection::class);
        $fileRepository = StaticEntityRepository::of(ChannelFileCollection::class);

        $tester = new CommandTester(new ChannelFileEnableCommand($channelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['foo']]));
        static::assertSame(
            'Ignored invalid id(s): foo' . \PHP_EOL . 'No files were enabled. No Frontend or Headless sales channel matches.',
            $tester->getDisplay(),
        );
        static::assertSame([], $fileRepository->creates);
    }

    private function file(string $channelId, string $fileName, bool $enabled): ChannelFileEntity
    {
        $file = new ChannelFileEntity();
        $file->setId(Uuid::randomHex());
        $file->setChannelId($channelId);
        $file->setFileFamily('agentic');
        $file->setFileName($fileName);
        $file->setEnabled($enabled);

        return $file;
    }
}
