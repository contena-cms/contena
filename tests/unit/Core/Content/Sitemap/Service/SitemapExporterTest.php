<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Contena\Core\Checkout\Cart\CartRuleLoader;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Contena\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Contena\Core\Content\Sitemap\SitemapException;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(SitemapExporter::class)]
class SitemapExporterTest extends TestCase
{
    public function testGenerate(): void
    {
        $urlItems = [
            [
                'url' => '',
            ],
            [
                'url' => 'test/',
            ],
            [
                'url' => 'test',
            ],
        ];

        $urls = [];
        foreach ($urlItems as $item) {
            $url = new Url();
            $url->setLoc($item['url']);

            $urls[] = $url;
        }

        $urlResult = new UrlResult($urls, null);

        $userUrlProvider = $this->createMock(CustomUrlProvider::class);
        $userUrlProvider->expects($this->once())->method('getUrls')->willReturn($urlResult);

        $sitemapHandler1 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandler2 = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandlerFactory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandlerFactory->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls(
                $sitemapHandler1,
                $sitemapHandler2
            );

        $cacheItemPoolInterface = static::createStub(CacheItemPoolInterface::class);
        $cacheItemPoolInterface->method('getItem')->willReturn(new CacheItem());

        $exporter = $this->createSitemapExporter($cacheItemPoolInterface, [$userUrlProvider], $sitemapHandlerFactory);

        $languageId = Uuid::randomHex();
        $channel = $this->createChannel('testChannel', $languageId);

        $domainA = $this->createChannelDomain('testDomainA', 'https://test.com/', $languageId);
        $domainB = $this->createChannelDomain('testDomainB', 'https://test.com', $languageId);

        $channel->setDomains(new ChannelDomainCollection([$domainA, $domainB]));

        $channelContext = $this->createChannelContext($channel, []);

        $expectedUrls = [];
        foreach ($urls as $url) {
            $expectedUrl = clone $url;
            $expectedUrl->setLoc('https://test.com/' . $url->getLoc());
            $expectedUrls[] = $expectedUrl;
        }

        $sitemapHandler1->expects($this->once())->method('write')->with($expectedUrls);
        $sitemapHandler2->expects($this->once())->method('write')->with($expectedUrls);
        $exporter->generate($channelContext);
    }

    public function testDoesNotRefreshChannelWithRules(): void
    {
        $channel = $this->createChannel('channelWithRules');
        $rules = array_map(static fn () => Uuid::randomHex(), range(0, 2));

        $domain = $this->createChannelDomain('testDomain', 'https://test.com', $channel->getLanguageId());
        $channel->setDomains(new ChannelDomainCollection([$domain]));

        $channelContext = $this->createChannelContext($channel, $rules);

        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $exporter = $this->createSitemapExporter(cache: $cache, cartRuleLoader: $cartRuleLoader);
        $exporter->generate($channelContext);

        $cartRuleLoader->expects($this->never())->method('loadByToken');
    }

    public function testGenerateSkipsHeadlessChannelWithoutExternalFrontendDomain(): void
    {
        $urlProvider = $this->createMock(CustomUrlProvider::class);
        $urlProvider->expects($this->never())->method('getUrls');

        $sitemapHandlerFactory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandlerFactory->expects($this->never())->method('create');

        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());

        $exporter = $this->createSitemapExporter($cache, [$urlProvider], $sitemapHandlerFactory);

        $languageId = Uuid::randomHex();
        $channel = $this->createChannel('headlessChannel', $languageId, typeId: Defaults::CHANNEL_TYPE_API);
        $channel->setDomains(new ChannelDomainCollection([
            $this->createChannelDomain('plainDomain', 'default.headless0', $languageId),
        ]));

        $result = $exporter->generate($this->createChannelContext($channel, []));

        static::assertTrue($result->isFinish());
    }

    public function testGenerateWritesOnlyExternalFrontendDomainsForHeadlessChannel(): void
    {
        $url = new Url();
        $url->setLoc('awesome-blog');

        $urlProvider = $this->createMock(CustomUrlProvider::class);
        $urlProvider->expects($this->once())->method('getUrls')->willReturn(new UrlResult([$url], null));

        $sitemapHandle = $this->createMock(SitemapHandleInterface::class);
        $sitemapHandle->expects($this->once())->method('write')->willReturnCallback(static function (array $urls): void {
            static::assertCount(1, $urls);
            static::assertInstanceOf(Url::class, $urls[0]);
            static::assertSame('https://frontend.example/awesome-blog', $urls[0]->getLoc());
        });

        $sitemapHandlerFactory = $this->createMock(SitemapHandleFactoryInterface::class);
        $sitemapHandlerFactory->expects($this->once())
            ->method('create')
            ->with(static::anything(), static::anything(), 'https://frontend.example', 'externalDomain')
            ->willReturn($sitemapHandle);

        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItem());

        $exporter = $this->createSitemapExporter($cache, [$urlProvider], $sitemapHandlerFactory);

        $languageId = Uuid::randomHex();
        $channel = $this->createChannel('headlessChannel', $languageId, typeId: Defaults::CHANNEL_TYPE_API);
        $channel->setDomains(new ChannelDomainCollection([
            $this->createChannelDomain('externalDomain', 'https://frontend.example', $languageId, isExternalFrontend: true),
            $this->createChannelDomain('plainDomain', 'default.headless0', $languageId),
        ]));

        $result = $exporter->generate($this->createChannelContext($channel, []));

        static::assertTrue($result->isFinish());
    }

    public function testGenerateThrowsExceptionINoSitemapHandlesCreated(): void
    {
        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItemMock());

        $exporter = $this->createSitemapExporter($cache);

        $channel = $this->createChannel('testChannel');
        $channelContext = $this->createChannelContext($channel, []);

        $this->expectExceptionObject(SitemapException::invalidDomain());
        $exporter->generate($channelContext, true);
    }

    public function testGenerateThrowsExceptionIfSitemapIsAlreadyLocked(): void
    {
        $cache = static::createStub(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn(new CacheItemMock());

        $exporter = $this->createSitemapExporter($cache);

        $channel = $this->createChannel('testChannel');
        $channelContext = $this->createChannelContext($channel, []);

        $this->expectExceptionObject(SitemapException::sitemapAlreadyLocked($channelContext));
        $exporter->generate($channelContext);
    }

    /**
     * @param iterable<AbstractUrlProvider>|null $urlProvider
     */
    private function createSitemapExporter(
        CacheItemPoolInterface&Stub $cache,
        ?iterable $urlProvider = null,
        (SitemapHandleFactoryInterface&MockObject)|null $sitemapHandleFactory = null,
        ?CartRuleLoader $cartRuleLoader = null
    ): SitemapExporter {
        return new SitemapExporter(
            $urlProvider ?? [],
            $cache,
            10,
            static::createStub(FilesystemOperator::class),
            $sitemapHandleFactory ?? static::createStub(SitemapHandleFactoryInterface::class),
            static::createStub(EventDispatcher::class),
            $cartRuleLoader ?? static::createStub(CartRuleLoader::class)
        );
    }

    private function createChannel(
        string $channelId,
        ?string $languageId = null,
        string $typeId = Defaults::CHANNEL_TYPE_FRONTEND
    ): ChannelEntity {
        $channel = new ChannelEntity();
        $channel->setId($channelId);
        $channel->setLanguageId($languageId ?? Uuid::randomHex());
        $channel->setTypeId($typeId);

        return $channel;
    }

    private function createChannelDomain(
        string $domainId,
        string $domainUrl,
        ?string $languageId = null,
        bool $isExternalFrontend = false
    ): ChannelDomainEntity {
        $channelDomain = new ChannelDomainEntity();
        $channelDomain->setId($domainId);
        $channelDomain->setUrl($domainUrl);
        $channelDomain->setLanguageId($languageId ?? Uuid::randomHex());
        $channelDomain->setIsExternalFrontend($isExternalFrontend);

        return $channelDomain;
    }

    /**
     * @param array<string> $ruleIds
     */
    private function createChannelContext(ChannelEntity $channel, array $ruleIds): ChannelContext
    {
        $context = new Context(
            source: new SystemSource(),
            ruleIds: $ruleIds,
            languageIdChain: [$channel->getLanguageId()],
        );

        return Generator::generateChannelContext(
            baseContext: $context,
            channel: $channel,
        );
    }
}

/**
 * @internal
 */
class CacheItemMock implements CacheItemInterface
{
    public function getKey(): string
    {
        return Uuid::randomHex();
    }

    public function get(): mixed
    {
        return null;
    }

    public function isHit(): bool
    {
        return true;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }
}
