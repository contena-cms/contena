<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Contena\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Contena\Core\Content\Sitemap\SitemapException;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Generator;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class SitemapExporterTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    private ChannelContext $context;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createFrontendChannelContext(Uuid::randomHex(), 'sitemap-exporter-test');
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    public function testNotLocked(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, false));

        $exporter = $this->createSitemapExporter($cache);

        $result = $exporter->generate($this->context);

        static::assertTrue($result->isFinish());
    }

    public function testExpectAlreadyLockedException(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, true));

        $exporter = $this->createSitemapExporter($cache);

        $this->expectException(SitemapException::class);
        $exporter->generate($this->context);
    }

    public function testForce(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, true));

        $exporter = $this->createSitemapExporter($cache);

        $result = $exporter->generate($this->context, true);

        static::assertTrue($result->isFinish());
    }

    public function testLocksAndUnlocks(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cacheItem = null;
        $cache->method('getItem')->willReturnCallback(function (string $k) use (&$cacheItem) {
            if ($cacheItem === null) {
                $cacheItem = $this->createCacheItem($k, null, false);
            }

            return $cacheItem;
        });

        $cache->method('save')->willReturnCallback(function (CacheItemInterface $i) use (&$cacheItem): bool {
            self::assertInstanceOf(CacheItemInterface::class, $cacheItem);
            static::assertSame($cacheItem->getKey(), $i->getKey());
            $cacheItem = $this->createCacheItem($i->getKey(), $i->get(), true);

            return true;
        });

        $cache->method('deleteItem')->willReturnCallback(static function (string $k) use (&$cacheItem): bool {
            static::assertNotNull($cacheItem, 'Was not locked');
            static::assertSame($cacheItem->getKey(), $k);
            static::assertTrue($cacheItem->isHit(), 'Was not locked');

            return true;
        });

        $exporter = $this->createSitemapExporter($cache);

        $result = $exporter->generate($this->context);

        static::assertTrue($result->isFinish());
    }

    public function testWriteWithMultipleSchemesAndSameLanguage(): void
    {
        $channel = $this->channelRepository->search(
            $this->frontendChannelCriteria([$this->context->getChannelId()]),
            $this->context->getContext()
        )->getEntities()->first();
        static::assertNotNull($channel);

        $domain = $channel->getDomains()?->first();
        static::assertNotNull($domain);

        $this->channelRepository->update([
            [
                'id' => $this->context->getChannelId(),
                'domains' => [
                    [
                        'id' => Uuid::randomHex(),
                        'languageId' => $domain->getLanguageId(),
                        'url' => str_replace('http://', 'https://', (string) $domain->getUrl()),
                        'snippetSetId' => $domain->getSnippetSetId(),
                    ],
                ],
            ],
        ], $this->context->getContext());

        $channel = $this->channelRepository->search(
            $this->frontendChannelCriteria([$this->context->getChannelId()]),
            $this->context->getContext()
        )->getEntities()->first();
        static::assertNotNull($channel);

        $domains = $channel->getDomains();
        static::assertNotNull($domains);
        $languageIds = $domains->map(static fn (ChannelDomainEntity $channelDomain) => $channelDomain->getLanguageId());

        $languageIds = array_unique($languageIds);

        foreach ($languageIds as $languageId) {
            $channelContext = static::getContainer()->get(ChannelContextFactory::class)
                ->create('', $channel->getId(), [ChannelContextService::LANGUAGE_ID => $languageId]);

            $this->generateSitemap($channelContext, false);

            $files = $this->getFilesystem('contena.filesystem.sitemap')
                ->listContents('sitemap/channel-' . $channel->getId() . '-' . $channelContext->getLanguageId());

            static::assertCount(1, iterator_to_array($files));
        }
    }

    public function testGenerationWithSlashes(): void
    {
        $url1 = new Url();
        $url1->setLoc('/test-with-slash');
        $url1->setLastmod(new \DateTime());
        $url1->setChangefreq('daily');

        $url2 = new Url();
        $url2->setLoc('test-without-slash');
        $url2->setLastmod(new \DateTime());
        $url2->setChangefreq('daily');

        $urls = [$url1, $url2];

        $handler = $this->createMock(AbstractUrlProvider::class);
        $handler->expects($this->once())->method('getUrls')->willReturn(new UrlResult($urls, null));

        $factory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandleMock = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandleMock->expects($this->once())->method('write')->willReturnCallback(static function (array $urls): void {
            static::assertCount(2, $urls);
            static::assertInstanceOf(Url::class, $urls[0]);
            static::assertInstanceOf(Url::class, $urls[1]);
            static::assertSame('https://test.com/de/test-with-slash', $urls[0]->getLoc());
            static::assertSame('https://test.com/de/test-without-slash', $urls[1]->getLoc());
        });

        $factory->expects($this->once())->method('create')->willReturn($sitemapHandleMock);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($this->createCacheItem('', true, false));

        $exporter = $this->createSitemapExporter($cache, [$handler], $factory);

        $channel = Generator::generateChannelContext();
        $channel->getChannel()->setDomains(new ChannelDomainCollection([
            new ChannelDomainEntity()->assign(['id' => '11', 'url' => 'https://test.com/de', 'languageId' => Defaults::LANGUAGE_SYSTEM]),
        ]));

        $exporter->generate($channel);
    }

    private function createCacheItem(string $key, ?bool $value, ?bool $isHit): CacheItemInterface
    {
        $item = new CacheItem();

        $class = new \ReflectionClass(CacheItem::class);

        $class->getProperty('key')->setValue($item, $key);
        $class->getProperty('value')->setValue($item, $value);
        $class->getProperty('isHit')->setValue($item, $isHit);

        return $item;
    }

    /**
     * @param list<string> $ids
     */
    private function frontendChannelCriteria(array $ids): Criteria
    {
        $criteria = new Criteria($ids);
        $criteria->addAssociation('domains');
        $criteria->addFilter(new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('domains.id', null)]
        ));

        $criteria->addAssociation('type');
        $criteria->addFilter(new EqualsFilter('type.id', Defaults::CHANNEL_TYPE_WEB));

        return $criteria;
    }

    private function generateSitemap(
        ChannelContext $channelContext,
        bool $force,
        ?string $lastProvider = null,
        ?int $offset = null
    ): void {
        $result = static::getContainer()->get(SitemapExporter::class)->generate($channelContext, $force, $lastProvider, $offset);
        if (!$result->isFinish()) {
            $this->generateSitemap($channelContext, $force, $result->getProvider(), $result->getOffset());
        }
    }

    /**
     * @param iterable<AbstractUrlProvider>|null $urlProvider
     */
    private function createSitemapExporter(
        CacheItemPoolInterface&MockObject $cache,
        ?iterable $urlProvider = null,
        (SitemapHandleFactoryInterface&MockObject)|null $sitemapHandleFactory = null,
    ): SitemapExporter {
        return new SitemapExporter(
            $urlProvider ?? [],
            $cache,
            10,
            $this->createMock(FilesystemOperator::class),
            $sitemapHandleFactory ?? $this->createMock(SitemapHandleFactoryInterface::class),
            $this->createMock(EventDispatcher::class),
        );
    }
}
