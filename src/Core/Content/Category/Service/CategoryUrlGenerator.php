<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Service;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelEntity;

class CategoryUrlGenerator extends AbstractCategoryUrlGenerator
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRouteResolver $entityRouteResolver)
    {
    }

    public function getDecorated(): AbstractCategoryUrlGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generate(CategoryEntity $category, ?ChannelEntity $channel): ?string
    {
        if ($category->getType() === CategoryDefinition::TYPE_FOLDER) {
            return null;
        }

        $channelTypeId = $channel?->getTypeId();

        if ($category->getType() !== CategoryDefinition::TYPE_LINK) {
            return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $category->getId(), $channelTypeId);
        }

        $linkType = $category->getTranslation('linkType');
        $internalLink = $category->getTranslation('internalLink');

        if (!$internalLink && $linkType && $linkType !== CategoryDefinition::LINK_TYPE_EXTERNAL) {
            return null;
        }

        switch ($linkType) {
            case CategoryDefinition::LINK_TYPE_BLOG:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(BlogDefinition::ENTITY_NAME, $internalLink, $channelTypeId);

            case CategoryDefinition::LINK_TYPE_CATEGORY:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(CategoryDefinition::ENTITY_NAME, $internalLink, $channelTypeId);

            case CategoryDefinition::LINK_TYPE_LANDING_PAGE:
                return $this->entityRouteResolver->generateSeoUrlPlaceholder(LandingPageDefinition::ENTITY_NAME, $internalLink, $channelTypeId);

            case CategoryDefinition::LINK_TYPE_EXTERNAL:
            default:
                return $category->getTranslation('externalLink');
        }
    }
}
