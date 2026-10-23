<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\BlogSlider;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingConfig;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the blogs, slot and resolver context the blog slider processor tests share. The slot takes
 * the field configuration the test assembled, so no state lives here.
 *
 * @internal
 */
final class BlogSliderFixture
{
    public static function getBlogs(): BlogCollection
    {
        $blog = (new BlogEntity())->assign([
            'id' => 'blog-1',
            '_uniqueIdentifier' => 'blog-1',
            'isCloseout' => false,
            'variantListingConfig' => new VariantListingConfig(
                false,
                'blog-1',
                null
            ),
            'stock' => 12,
        ]);

        $blog2 = (new BlogEntity())->assign([
            'id' => 'blog-2',
            '_uniqueIdentifier' => 'blog-2',
            'isCloseout' => true,
            'stock' => 0,
        ]);

        return new BlogCollection([$blog, $blog2]);
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    public static function getEntitySearchResult(BlogCollection $blogs): EntitySearchResult
    {
        return new EntitySearchResult(
            'blog',
            $blogs->count(),
            $blogs,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    public static function getSlot(FieldConfigCollection $config): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-slider');
        $slot->setFieldConfig($config);

        return $slot;
    }

    public static function getResolverContext(): ResolverContext
    {
        return new ResolverContext(Generator::generateChannelContext(), new Request());
    }
}
