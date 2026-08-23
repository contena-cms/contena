<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Contena\Core\Content\Sitemap\Service\SitemapChannelProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
class SitemapGenerateTaskHandlerTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    private SitemapGenerateTaskHandler $sitemapHandler;

    /**
     * @var EntityRepository<ChannelDomainCollection>
     */
    private EntityRepository $channelDomainRepository;

    private MockObject&MessageBusInterface $messageBusMock;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $this->sitemapHandler = new SitemapGenerateTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            static::getContainer()->get(SitemapChannelProvider::class),
            $this->systemConfigService,
            $this->messageBusMock,
            static::getContainer()->get('event_dispatcher')
        );
        $this->channelDomainRepository = static::getContainer()->get('channel_domain.repository');
    }

    public function testNotHandelDuplicateWithSameLanguage(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM channel');
        $tenantContext = $this->createTenantContext($this->createTenant());

        $channelContext = $this->createFrontendChannelContext(
            Uuid::randomHex(),
            'test-sitemap-task-handler',
            context: $tenantContext,
        );

        $this->channelDomainRepository->create([
            [
                'channelId' => $channelContext->getChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.com',
            ],
            [
                'channelId' => $channelContext->getChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.de',
            ],
        ], $tenantContext);

        $message = new SitemapMessage(
            $channelContext->getChannelId(),
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true,
            $tenantContext->getTenantId(),
        );

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testItGeneratesCorrectMessagesIfLastLanguageIsFirstOfNextChannel(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM channel');
        $tenantContext = $this->createTenantContext($this->createTenant());
        $firstChannelId = TestDefaults::CHANNEL;
        $secondChannelId = substr_replace($firstChannelId, 'f', 0, 1);

        $this->createFrontendChannelContext($firstChannelId, 'first-channel', context: $tenantContext);
        $this->createFrontendChannelContext($secondChannelId, 'second-channel', context: $tenantContext);

        $message = new SitemapMessage(
            $firstChannelId,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true,
            $tenantContext->getTenantId(),
        );

        $this->messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
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
        $message = new SitemapMessage(
            $frontendId,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            false,
            $tenantContext->getTenantId(),
        );

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testTenantStrategyOverridesPlatformWhileOtherTenantFallsBack(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM channel');
        $platformContext = Context::createDefaultContext();
        $tenantAContext = $this->createTenantContext($this->createTenant('Sitemap disabled tenant'));
        $tenantBContext = $this->createTenantContext($this->createTenant('Sitemap fallback tenant'));

        $this->systemConfigService->set(
            'core.sitemap.sitemapRefreshStrategy',
            SitemapExporterInterface::STRATEGY_SCHEDULED_TASK,
            context: $platformContext,
        );
        $this->systemConfigService->set(
            'core.sitemap.sitemapRefreshStrategy',
            SitemapExporterInterface::STRATEGY_MANUAL,
            context: $tenantAContext,
        );

        $this->createFrontendChannelContext(Uuid::randomHex(), 'platform sitemap', context: $platformContext);
        $this->createFrontendChannelContext(Uuid::randomHex(), 'disabled tenant sitemap', context: $tenantAContext);
        $this->createFrontendChannelContext(Uuid::randomHex(), 'fallback tenant sitemap', context: $tenantBContext);

        $messageTenantIds = [];
        $this->messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (SitemapMessage $message) use (&$messageTenantIds): Envelope {
                $messageTenantIds[] = $message->getTenantId();

                return new Envelope($message);
            });

        $this->sitemapHandler->run();

        static::assertSame([null, $tenantBContext->getTenantId()], $messageTenantIds);
    }
}
