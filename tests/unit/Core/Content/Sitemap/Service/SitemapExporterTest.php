<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Content\Sitemap\Provider\CustomUrlProvider;
use Contena\Core\Content\Sitemap\Service\SitemapExporter;
use Contena\Core\Content\Sitemap\Service\SitemapHandleFactoryInterface;
use Contena\Core\Content\Sitemap\Service\SitemapHandleInterface;
use Contena\Core\Content\Sitemap\SitemapException;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
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

        $customerUrlProvider = $this->createMock(CustomUrlProvider::class);
        $customerUrlProvider->expects($this->once())->method('getUrls')->willReturn($urlResult);

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

        $exporter = $this->createSitemapExporter($cacheItemPoolInterface, [$customerUrlProvider], $sitemapHandlerFactory);

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
    ): SitemapExporter {
        return new SitemapExporter(
            $urlProvider ?? [],
            $cache,
            10,
            static::createStub(FilesystemOperator::class),
            $sitemapHandleFactory ?? static::createStub(SitemapHandleFactoryInterface::class),
            static::createStub(EventDispatcher::class),
        );
    }

    private function createChannel(
        string $channelId,
        ?string $languageId = null
    ): ChannelEntity {
        $channel = new ChannelEntity();
        $channel->setId($channelId);
        $channel->setLanguageId($languageId ?? Uuid::randomHex());

        return $channel;
    }

    private function createChannelDomain(
        string $domainId,
        string $domainUrl,
        ?string $languageId = null
    ): ChannelDomainEntity {
        $channelDomain = new ChannelDomainEntity();
        $channelDomain->setId($domainId);
        $channelDomain->setUrl($domainUrl);
        $channelDomain->setLanguageId($languageId ?? Uuid::randomHex());

        return $channelDomain;
    }

    /**
     * @param list<string> $ruleIds
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
