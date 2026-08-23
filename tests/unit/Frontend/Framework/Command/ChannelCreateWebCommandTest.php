<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\System\Snippet\Aggregate\SnippetSet\SnippetSetCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Framework\Command\ChannelCreateWebCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ChannelCreateWebCommand::class)]
class ChannelCreateWebCommandTest extends TestCase
{
    public function testExecuteUsesExplicitSnippetSet(): void
    {
        $options = [
            '--id' => Uuid::randomHex(),
            '--name' => 'Web',
            '--languageId' => Uuid::randomHex(),
            '--countryId' => Uuid::randomHex(),
            '--memberGroupId' => Uuid::randomHex(),
            '--navigationCategoryId' => Uuid::randomHex(),
            '--url' => 'https://web.example.test',
            '--snippetSetId' => Uuid::randomHex(),
        ];
        $accessKey = AccessKeyHelper::generateAccessKey('channel');
        $creator = $this->createMock(ChannelCreator::class);
        $creator->expects($this->once())
            ->method('createChannel')
            ->with(
                $options['--id'],
                'Web',
                Defaults::CHANNEL_TYPE_WEB,
                $options['--languageId'],
                $options['--countryId'],
                $options['--memberGroupId'],
                $options['--navigationCategoryId'],
                null,
                null,
                [
                    'domains' => [[
                        'url' => $options['--url'],
                        'languageId' => $options['--languageId'],
                        'snippetSetId' => $options['--snippetSetId'],
                    ]],
                    'navigationCategoryDepth' => 3,
                    'name' => 'Web',
                ]
            )
            ->willReturn($accessKey);

        $snippetRepository = StaticEntityRepository::of(SnippetSetCollection::class);
        $commandTester = new CommandTester(new ChannelCreateWebCommand($snippetRepository, $creator));

        static::assertSame(Command::SUCCESS, $commandTester->execute($options));
        static::assertStringContainsString($accessKey, $commandTester->getDisplay());
    }
}
