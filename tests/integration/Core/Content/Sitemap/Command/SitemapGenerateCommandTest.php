<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\Commands\SitemapGenerateCommand;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Symfony\Component\Console\Input\ArrayInput;
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
            static::getContainer()->get(SitemapChannelProvider::class),
            $this->exporter,
            static::getContainer()->get(ChannelContextFactory::class),
            $this->createMock(EventDispatcher::class)
        );
    }

    public function testSkipNonFrontendChannels(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM channel');
        $tenantContext = $this->createTenantContext($this->createTenant());

        $frontendId = Uuid::randomHex();
        $this->createChannel([
            'id' => $frontendId,
            'name' => 'frontend',
            'typeId' => Defaults::CHANNEL_TYPE_WEB,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://valid.test',
            ]],
        ], $tenantContext);
        $this->createChannel([
            'name' => 'api',
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://api.test',
            ]],
        ], $tenantContext);
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
}
