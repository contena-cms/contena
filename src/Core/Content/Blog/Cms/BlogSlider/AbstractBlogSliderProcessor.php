<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms\BlogSlider;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Log\Package;

abstract class AbstractBlogSliderProcessor
{
    protected const BLOG_SLIDER_ENTITY_FALLBACK = 'blog-slider-entity-fallback';

    abstract public function getDecorated(): AbstractBlogSliderProcessor;

    abstract public function getSource(): string;

    abstract public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection;

    abstract public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void;

    protected function filterOutOutOfStockHiddenCloseoutBlogs(BlogCollection $blogs): BlogCollection
    {
        return $blogs->filter(static function (BlogEntity $blog) {
            if ($blog->getChildCount() > 0) {
                return true;
            }

            if ($blog->getIsCloseout() && $blog->getStock() <= 0) {
                return false;
            }

            return true;
        });
    }
}
