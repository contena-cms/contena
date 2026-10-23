<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Seo;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class FrontendCategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    private const HOME_PAGE_ROUTE = 'frontend.home.page';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCategoryUrlGenerator $decorated,
        private readonly RouterInterface $router,
    ) {
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        return $this->decorated;
    }

    public function generate(CategoryEntity $category, ?ChannelEntity $channel): ?string
    {
        if ($channel !== null && $this->isHomePageLink($category, $channel)) {
            return $this->router->generate(self::HOME_PAGE_ROUTE);
        }

        return $this->getDecorated()->generate($category, $channel);
    }

    private function isHomePageLink(CategoryEntity $category, ChannelEntity $channel): bool
    {
        if (
            $category->getType() !== CategoryDefinition::TYPE_LINK
            || $category->getTranslation('linkType') !== CategoryDefinition::LINK_TYPE_CATEGORY
        ) {
            return false;
        }

        return $category->getTranslation('internalLink') === $channel->getNavigationCategoryId();
    }
}
