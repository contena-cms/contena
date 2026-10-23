<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Contena\Core\Content\Sitemap\Service\SitemapChannelLoader;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class SitemapGenerateCommandTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private MockObject&SitemapExporter $exporter;

    private SitemapGenerateCommand $command;

    protected function setUp(): void
    {
        $this->exporter = $this->createMock(SitemapExporter::class);

        $this->command = new SitemapGenerateCommand(
            new SitemapChannelLoader(
                static::getContainer()->get('channel.repository'),
                static::createStub(EventDispatcher::class)
            ),
            $this->exporter,
            static::getContainer()->get(ChannelContextFactory::class)
        );
    }

    public function testSkipNonFrontendChannels(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM channel');

        $frontendId = Uuid::randomHex();
        $this->createChannel([
            'id' => $frontendId,
            'name' => 'frontend',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://valid.test',
            ]],
        ]);
        $this->createChannel([
            'name' => 'api',
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://api.test',
            ]],
        ]);
        $this->createChannel([
            'name' => 'export',
            'typeId' => Defaults::CHANNEL_TYPE_BLOG_COMPARISON,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://export.test',
            ]],
        ]);

        $result = new SitemapGenerationResult(true, null, null, $frontendId, Defaults::LANGUAGE_SYSTEM);

        $this->exporter->expects($this->once())
            ->method('generate')
            ->with(static::callback(static function (ChannelContext $context) use ($frontendId) {
                static::assertSame($frontendId, $context->getChannelId());

                return true;
            }))
            ->willReturn($result);

        $input = new ArrayInput([]);
        $this->command->run($input, new NullOutput());
    }

    public function testContinuesWhenSitemapGenerationIsLocked(): void
    {
        // this test runs its own command against the real exporter, the shared double stays untouched
        $this->exporter->expects($this->never())->method(static::anything());

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM channel');

        $frontendId = Uuid::randomHex();
        $this->createChannel([
            'id' => $frontendId,
            'name' => 'frontend',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://valid.test',
            ]],
        ]);

        // hold the lock like a concurrently running generation would
        $cache = static::getContainer()->get('cache.system');
        $lockKey = \sprintf('sitemap-exporter-running-%s-%s', $frontendId, Defaults::LANGUAGE_SYSTEM);
        $lock = $cache->getItem($lockKey);
        $lock->set(true);
        $cache->save($lock);

        $command = new SitemapGenerateCommand(
            new SitemapChannelLoader(
                static::getContainer()->get('channel.repository'),
                static::createStub(EventDispatcher::class)
            ),
            static::getContainer()->get(SitemapExporter::class),
            static::getContainer()->get(ChannelContextFactory::class)
        );

        $output = new BufferedOutput();

        try {
            $status = $command->run(new ArrayInput([]), $output);
        } finally {
            $cache->deleteItem($lockKey);
        }

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('ERROR: Cannot acquire lock', $output->fetch());
    }

    public function testGeneratesHeadlessChannelWithExternalFrontendDomain(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM channel');

        $headlessId = Uuid::randomHex();
        $this->createChannel([
            'id' => $headlessId,
            'name' => 'headless',
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://frontend.test',
                    'isExternalFrontend' => true,
                ],
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://api.test',
                ],
            ],
        ]);

        $result = new SitemapGenerationResult(true, null, null, $headlessId, Defaults::LANGUAGE_SYSTEM);

        // exactly one generation run: only the external frontend domain qualifies the language
        $this->exporter->expects($this->once())
            ->method('generate')
            ->with(static::callback(static function (ChannelContext $context) use ($headlessId) {
                static::assertSame($headlessId, $context->getChannelId());
                static::assertSame(Defaults::LANGUAGE_SYSTEM, $context->getLanguageId());

                return true;
            }))
            ->willReturn($result);

        $input = new ArrayInput([]);
        $this->command->run($input, new NullOutput());
    }
}
