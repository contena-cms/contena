<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;

class HreflangLoaderParameter
{
    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        protected string $route,
        protected array $routeParameters,
        protected ChannelContext $channelContext,
        private readonly bool $homepage = false,
        private readonly string $basePath = '',
    ) {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function isHomepage(): bool
    {
        if ($this->homepage) {
            return true;
        }

        // @deprecated tag:v6.8.0 - BC fallback for callers built before the $homepage flag existed; remove together with this condition
        /** @phpstan-ignore contena.frontendRouteUsage (No Frontend route is resolved here; the caller-provided route name is only compared to restore the pre-6.7 behaviour. Removed together with the deprecated fallback in v6.8.0) */
        if ($this->route === 'frontend.home.page') {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                'Deriving the homepage from the route name is deprecated and will be removed in v6.8.0.0. Construct HreflangLoaderParameter with $homepage set to true instead.'
            );

            return true;
        }

        return false;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
