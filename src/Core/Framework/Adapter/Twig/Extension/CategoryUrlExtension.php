<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Twig\Extension;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\Framework\Adapter\Twig\TwigContextHelper;
use Contena\Core\Framework\Feature;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryUrlExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RoutingExtension $routingExtension,
        private readonly AbstractCategoryUrlGenerator $categoryUrlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_url', $this->getCategoryUrl(...), ['needs_context' => true, 'is_safe_callback' => $this->routingExtension->isUrlGenerationSafe(...)]),
            new TwigFunction('category_linknewtab', $this->isLinkNewTab(...)),
        ];
    }

    /**
     * @param array<string, mixed> $twigContext
     *
     * @deprecated tag:v6.9.0 - Use ChannelCategoryEntity::getSeoUrl() instead.
     */
    public function getCategoryUrl(array $twigContext, CategoryEntity $category): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            'The "category_url" function is deprecated and will be removed in v6.9.0.0. Use ChannelCategoryEntity::getSeoUrl() instead.',
            silentUntil: 'v6.8.0.0',
        );

        if ($category instanceof ChannelCategoryEntity) {
            return $category->getSeoUrl();
        }

        $channel = TwigContextHelper::getChannelContext($twigContext)?->getChannel();

        return $this->categoryUrlGenerator->generate($category, $channel);
    }

    /**
     * @deprecated tag:v6.9.0 - Use CategoryEntity::shouldOpenInNewTab() instead.
     */
    public function isLinkNewTab(CategoryEntity $categoryEntity): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.9.0.0',
            'The "category_linknewtab" function is deprecated and will be removed in v6.9.0.0. Use CategoryEntity::shouldOpenInNewTab() instead.',
            silentUntil: 'v6.8.0.0',
        );

        return $categoryEntity->shouldOpenInNewTab();
    }
}
