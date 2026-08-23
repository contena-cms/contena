<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class SeoUrlFunctionExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RoutingExtension $routingExtension,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoUrl', $this->seoUrl(...), ['is_safe_callback' => $this->routingExtension->isUrlGenerationSafe(...)]),
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function seoUrl(string $name, array $parameters = []): string
    {
        return $this->seoUrlReplacer->generate($name, $parameters);
    }
}
