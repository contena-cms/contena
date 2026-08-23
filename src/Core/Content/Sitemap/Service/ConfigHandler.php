<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Contena\Core\Content\Sitemap\SitemapException;

/**
 * @phpstan-type UrlsConfig array<array<string, mixed>>
 */
class ConfigHandler
{
    final public const string EXCLUDED_URLS_KEY = 'excluded_urls';
    final public const string CUSTOM_URLS_KEY = 'custom_urls';

    /**
     * @internal
     *
     * @param ConfigHandlerInterface[] $configHandlers
     */
    public function __construct(private readonly iterable $configHandlers)
    {
    }

    /**
     * @return UrlsConfig
     */
    public function get(string $key): array
    {
        $filteredUrls = [];
        $customUrls = [];

        foreach ($this->configHandlers as $configHandler) {
            $config = $configHandler->getSitemapConfig();
            $filteredUrls = $this->addUrls($filteredUrls, $config[self::EXCLUDED_URLS_KEY]);
            $customUrls = $this->addUrls($customUrls, $config[self::CUSTOM_URLS_KEY]);
        }

        if ($key === self::EXCLUDED_URLS_KEY) {
            return $filteredUrls;
        }

        if ($key === self::CUSTOM_URLS_KEY) {
            return $customUrls;
        }

        throw SitemapException::invalidKey($key);
    }

    /**
     * @param UrlsConfig $urls
     * @param UrlsConfig $config
     *
     * @return UrlsConfig
     */
    private function addUrls(array $urls, array $config): array
    {
        foreach ($config as $configUrl) {
            $urls[] = $configUrl;
        }

        return $urls;
    }
}
