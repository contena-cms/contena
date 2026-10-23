<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Contena\Core\PlatformRequest;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ThemeAssetPackage extends FallbackUrlPackage
{
    /**
     * @internal
     *
     * @param string|list<string> $baseUrls
     */
    public function __construct(
        string|array $baseUrls,
        VersionStrategyInterface $versionStrategy,
        private readonly RequestStack $requestStack,
        private readonly AbstractThemePathBuilder $themePathBuilder
    ) {
        parent::__construct($baseUrls, $versionStrategy);
    }

    public function getUrl(string $path): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        $url = $path;
        if ($url && $url[0] !== '/') {
            $url = '/' . $url;
        }

        $url = $this->getVersionStrategy()->applyVersion($this->prependThemePath($url) . $url);

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        return $this->getBaseUrl($path) . $url;
    }

    private function prependThemePath(string $url): string
    {
        $currentRequest = $this->requestStack->getMainRequest();

        if ($currentRequest === null) {
            return '';
        }

        $channelId = $currentRequest->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID);
        $themeId = $currentRequest->attributes->get(ChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null || $channelId === null) {
            return '';
        }

        if (str_starts_with($url, '/assets')) {
            return '/theme/' . $themeId;
        }

        return '/theme/' . $this->themePathBuilder->assemblePath($channelId, $themeId);
    }
}
