<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler;
use Contena\Core\Content\Sitemap\ScheduledTask\SitemapMessage;
use Contena\Core\Content\Sitemap\Service\SitemapChannelLoader;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class SitemapGenerateTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelFunctionalTestBehaviour;
    use FrontendChannelTestHelper;

    private SitemapGenerateTaskHandler $sitemapHandler;

    /**
     * @var EntityRepository<ChannelDomainCollection>
     */
    private EntityRepository $channelDomainRepository;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    private MockObject&MessageBusInterface $messageBusMock;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->sitemapHandler = new SitemapGenerateTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            static::createStub(LoggerInterface::class),
            new SitemapChannelLoader($this->channelRepository, static::getContainer()->get('event_dispatcher')),
            static::getContainer()->get(SystemConfigService::class),
            $this->messageBusMock
        );
        $this->channelDomainRepository = static::getContainer()->get('channel_domain.repository');
    }

    public function testNotHandelDuplicateWithSameLanguage(): void
    {
        $channelIds = $this->channelRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $channelContext = $this->createFrontendChannelContext(Uuid::randomHex(), 'test-sitemap-task-handler');

        $nonDefaults = array_values(array_filter(array_map(static function (string $id): ?array {
            if ($id === TestDefaults::CHANNEL) {
                return null;
            }

            return ['id' => $id];
        }, $channelIds)));

        $this->channelRepository->delete($nonDefaults, Context::createDefaultContext());

        $this->channelDomainRepository->create([
            [
                'channelId' => $channelContext->getChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.com',
            ],
            [
                'channelId' => $channelContext->getChannelId(),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.de',
            ],
        ], Context::createDefaultContext());

        $message = new SitemapMessage(
            TestDefaults::CHANNEL,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true
        );

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testItGeneratesCorrectMessagesIfLastLanguageIsFirstOfNextChannel(): void
    {
        $channelIds = $this->channelRepository->searchIds(new Criteria(), Context::createDefaultContext())->getIds();

        $nonDefaults = array_values(array_filter(array_map(static function (string $id): ?array {
            if ($id === TestDefaults::CHANNEL) {
                return null;
            }

            return ['id' => $id];
        }, $channelIds)));

        $this->channelRepository->delete($nonDefaults, Context::createDefaultContext());

        // trick the sorting by making sure the new sales channel id is greater than the default sales channel id
        $newChannelId = substr_replace(TestDefaults::CHANNEL, 'f', 0, 1);

        $paymentMethod = $this->getAvailablePaymentMethod();

        $this->channelDomainRepository->create([
            [
                'channelId' => TestDefaults::CHANNEL,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.com',
            ],
            [
                'channelId' => $newChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'https://test.de',
                'channel' => [
                    'id' => $newChannelId,
                    'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
                    'name' => 'Test',
                    'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'currencyId' => Defaults::CURRENCY,
                    'paymentMethodId' => $paymentMethod->getId(),
                    'paymentMethods' => [['id' => $paymentMethod->getId()]],
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'navigationCategoryId' => $this->getValidCategoryId(),
                    'countryId' => $this->getValidCountryId(null),
                    'currencies' => [['id' => Defaults::CURRENCY]],
                    'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                    'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                ],
            ],
        ], Context::createDefaultContext());

        $message = new SitemapMessage(
            TestDefaults::CHANNEL,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            true
        );

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
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

        $message = new SitemapMessage(
            $frontendId,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            false
        );

        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }

    public function testDispatchesHeadlessChannelWithExternalFrontendDomain(): void
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

        $message = new SitemapMessage(
            $headlessId,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            false
        );

        // exactly one message: the external frontend domain qualifies the language once
        $this->messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $this->sitemapHandler->run();
    }
}
