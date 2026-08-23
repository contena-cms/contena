<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Psr\Cache\CacheItemPoolInterface;
use Contena\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Contena\Core\Content\Sitemap\Event\SitemapGenerationStartEvent;
use Contena\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Contena\Core\Content\Sitemap\SitemapException;
use Contena\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class SitemapExporter implements SitemapExporterInterface
{
    /**
     * @var array<string, SitemapHandleInterface>
     */
    private array $sitemapHandles = [];

    /**
     * @param iterable<AbstractUrlProvider> $urlProvider
     *
     * @internal
     */
    public function __construct(
        private readonly iterable $urlProvider,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $batchSize,
        private readonly FilesystemOperator $filesystem,
        private readonly SitemapHandleFactoryInterface $sitemapHandleFactory,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function generate(ChannelContext $context, bool $force = false, ?string $lastProvider = null, ?int $offset = null): SitemapGenerationResult
    {
        $this->dispatcher->dispatch(
            new SitemapGenerationStartEvent($context)
        );

        $this->lock($context, $force);

        try {
            $this->initSitemapHandles($context);

            foreach ($this->urlProvider as $urlProvider) {
                do {
                    $result = $urlProvider->getUrls($context, $this->batchSize, $offset);

                    $this->processSiteMapHandles($result);

                    $needRun = $result->getNextOffset() !== null;
                    $offset = $result->getNextOffset();
                } while ($needRun);
            }

            $this->finishSitemapHandles();
        } finally {
            $this->unlock($context);
        }

        $this->dispatcher->dispatch(new SitemapGeneratedEvent($context));

        return new SitemapGenerationResult(
            true,
            $lastProvider,
            null,
            $context->getChannelId(),
            $context->getLanguageId()
        );
    }

    private function lock(ChannelContext $channelContext, bool $force): void
    {
        $key = $this->generateCacheKeyForChannel($channelContext);
        $item = $this->cache->getItem($key);
        if ($item->isHit() && !$force) {
            throw SitemapException::sitemapAlreadyLocked($channelContext);
        }

        $item->set(true);
        $this->cache->save($item);
    }

    private function unlock(ChannelContext $channelContext): void
    {
        $this->cache->deleteItem($this->generateCacheKeyForChannel($channelContext));
    }

    private function generateCacheKeyForChannel(ChannelContext $channelContext): string
    {
        return \sprintf('sitemap-exporter-running-%s-%s', $channelContext->getChannelId(), $channelContext->getLanguageId());
    }

    private function initSitemapHandles(ChannelContext $context): void
    {
        $languageId = $context->getLanguageId();
        $domainsEntity = $context->getChannel()->getDomains();

        $sitemapDomains = [];
        if ($domainsEntity instanceof ChannelDomainCollection) {
            foreach ($domainsEntity as $domain) {
                if ($domain->getLanguageId() === $languageId) {
                    $urlParts = \parse_url($domain->getUrl());

                    if ($urlParts === false) {
                        continue;
                    }

                    $arrayKey = ($urlParts['host'] ?? '') . ($urlParts['path'] ?? '');

                    if (\array_key_exists($arrayKey, $sitemapDomains) && $sitemapDomains[$arrayKey]['scheme'] === 'https') {
                        continue;
                    }

                    $sitemapDomains[$arrayKey] = [
                        'domainId' => $domain->getId(),
                        'url' => $domain->getUrl(),
                        'scheme' => $urlParts['scheme'] ?? '',
                    ];
                }
            }
        }

        $sitemapHandles = [];
        foreach ($sitemapDomains as $sitemapDomain) {
            $sitemapHandles[$sitemapDomain['url']] = $this->sitemapHandleFactory->create($this->filesystem, $context, $sitemapDomain['url'], $sitemapDomain['domainId']);
        }

        if ($sitemapHandles === []) {
            throw SitemapException::invalidDomain();
        }

        $this->sitemapHandles = $sitemapHandles;
    }

    private function processSiteMapHandles(UrlResult $result): void
    {
        /** @var SitemapHandle $sitemapHandle */
        foreach ($this->sitemapHandles as $host => $sitemapHandle) {
            $urls = [];

            foreach ($result->getUrls() as $url) {
                $newUrl = clone $url;
                $newUrl->setLoc(rtrim($host, '/') . '/' . ltrim($newUrl->getLoc(), '/'));
                $urls[] = $newUrl;
            }

            $sitemapHandle->write($urls);
        }
    }

    private function finishSitemapHandles(): void
    {
        /** @var SitemapHandle $sitemapHandle */
        foreach ($this->sitemapHandles as $index => $sitemapHandle) {
            if ($index === array_key_first($this->sitemapHandles)) {
                $sitemapHandle->finish();

                continue;
            }

            $sitemapHandle->finish(false);
        }
    }
}
