<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

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
        return $this->homepage;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
