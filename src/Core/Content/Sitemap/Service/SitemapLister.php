<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Sitemap\Struct\Sitemap;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Asset\Package;

class SitemapLister implements SitemapListerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly Package $package,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getSitemaps(ChannelContext $channelContext): array
    {
        $files = $this->filesystem->listContents('sitemap/channel-' . $channelContext->getChannelId() . '-' . $channelContext->getLanguageId());

        $sitemaps = [];

        /** @var ChannelDomainCollection $domains */
        $domains = $channelContext->getChannel()->getDomains();

        foreach ($files as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filename = basename($file->path());

            $exploded = explode('-', $filename);

            if (isset($exploded[1]) && $domains->has($exploded[1])) {
                $domain = $domains->get($exploded[1]);

                $sitemaps[] = new Sitemap($domain->getUrl() . '/' . $file->path(), 0, new \DateTime('@' . ($file->lastModified() ?? $this->clock->now()->getTimestamp())));

                continue;
            }

            $sitemaps[] = new Sitemap($this->package->getUrl($file->path()), 0, new \DateTime('@' . ($file->lastModified() ?? $this->clock->now()->getTimestamp())));
        }

        return $sitemaps;
    }
}
