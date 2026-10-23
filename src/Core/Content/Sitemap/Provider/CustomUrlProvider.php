<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Provider;

use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @phpstan-import-type UrlsConfig from ConfigHandler
 */
class CustomUrlProvider extends AbstractUrlProvider
{
    /**
     * @internal
     */
    public function __construct(private readonly ConfigHandler $configHandler)
    {
    }

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function getUrls(ChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $sitemapCustomUrls = $this->configHandler->get(ConfigHandler::CUSTOM_URLS_KEY);

        $urls = [];
        $url = new Url();
        foreach ($sitemapCustomUrls as $sitemapCustomUrl) {
            if (!$this->isAvailableForChannel($sitemapCustomUrl, $context->getChannelId())) {
                continue;
            }

            $newUrl = clone $url;
            $newUrl->setLoc($sitemapCustomUrl['url']);
            $newUrl->setLastmod($sitemapCustomUrl['lastMod']);
            $newUrl->setChangefreq($sitemapCustomUrl['changeFreq']);
            $newUrl->setResource('custom');
            $newUrl->setIdentifier('');

            $urls[] = $newUrl;
        }

        return new UrlResult($urls, null);
    }

    /**
     * @param UrlsConfig $url
     */
    private function isAvailableForChannel(array $url, ?string $channelId): bool
    {
        return \in_array($url['channelId'], [$channelId, null], true);
    }
}
