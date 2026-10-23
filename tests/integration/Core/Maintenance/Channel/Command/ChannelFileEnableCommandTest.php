<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Maintenance\Channel\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Command\ChannelFileEnableCommand;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ChannelFileEnableCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CommandTester $commandTester;

    /**
     * @var EntityRepository<ChannelFileCollection>
     */
    private EntityRepository $fileRepository;

    private string $frontendId;

    protected function setUp(): void
    {
        $this->commandTester = new CommandTester(static::getContainer()->get(ChannelFileEnableCommand::class));
        $this->fileRepository = static::getContainer()->get('channel_file.repository');
        $this->frontendId = $this->channelIdOfType(Defaults::CHANNEL_TYPE_FRONTEND);
    }

    public function testItEnablesLlmsTxtAndAgentsMd(): void
    {
        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['ids' => [$this->frontendId]]));

        static::assertSame(['AGENTS.md' => true, 'llms.txt' => true], $this->files($this->frontendId));
    }

    public function testAFileTheMerchantSwitchedOffStaysOff(): void
    {
        $this->fileRepository->create([[
            'channelId' => $this->frontendId,
            'fileFamily' => 'agentic',
            'fileName' => 'agents.md',
            'enabled' => false,
        ]], Context::createDefaultContext());

        $this->commandTester->execute(['ids' => [$this->frontendId]]);

        static::assertSame(['agents.md' => false, 'llms.txt' => true], $this->files($this->frontendId));
    }

    public function testASecondRunChangesNothing(): void
    {
        $this->commandTester->execute(['--all' => true]);
        $filesAfterFirstRun = $this->files($this->frontendId);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--all' => true]));
        static::assertSame(
            'No files were enabled. The files already exist for every selected sales channel.',
            $this->commandTester->getDisplay(),
        );
        static::assertSame($filesAfterFirstRun, $this->files($this->frontendId));
    }

    /**
     * @return array<string, bool>
     */
    private function files(string $channelId): array
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('channelId', $channelId),
            new EqualsFilter('fileFamily', 'agentic'),
        );

        $files = [];
        foreach ($this->fileRepository->search($criteria, Context::createDefaultContext())->getEntities() as $file) {
            $files[$file->getFileName()] = $file->isEnabled();
        }
        ksort($files);

        return $files;
    }

    private function channelIdOfType(string $typeId): string
    {
        $channelRepository = static::getContainer()->get('channel.repository');
        static::assertInstanceOf(EntityRepository::class, $channelRepository);
        $criteria = (new Criteria())->addFilter(new EqualsFilter('typeId', $typeId))->setLimit(1);

        $channelId = $channelRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($channelId);
        static::assertTrue(Uuid::isValid($channelId));

        return $channelId;
    }
}
