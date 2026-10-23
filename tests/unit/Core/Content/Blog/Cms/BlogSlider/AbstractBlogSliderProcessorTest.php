<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\BlogSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Cms\BlogSlider\AbstractBlogSliderProcessor;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(AbstractBlogSliderProcessor::class)]
class AbstractBlogSliderProcessorTest extends TestCase
{
    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        (new TestAbstractBlogSliderProcessor())->getDecorated();
    }

    public function testGetSource(): void
    {
        $processor = new TestAbstractBlogSliderProcessor();
        static::assertSame('test', $processor->getSource());
    }

    public function testFilterOutOutOfStockHiddenCloseoutBlogs(): void
    {
        $blogs = BlogSliderFixture::getBlogs();
        static::assertCount(2, $blogs);

        $processor = new TestAbstractBlogSliderProcessor();

        $filteredBlogs = $processor->publicFilterOutOutOfStockHiddenCloseoutBlogs($blogs);
        static::assertCount(1, $filteredBlogs);
    }

    public function testFilterOutOutOfStockHiddenCloseoutBlogsForVariantBlogs(): void
    {
        $blog1Variant1 = (new BlogEntity())->assign(['id' => 'p1v1', 'stock' => 10]);
        $blog1Variant2 = (new BlogEntity())->assign(['id' => 'p1v2', 'stock' => 0]);
        $blog1 = (new BlogEntity())->assign([
            'id' => 'p1',
            'isCloseout' => true,
            'childCount' => 2,
            'children' => new BlogCollection([$blog1Variant1, $blog1Variant2]),
        ]);

        $blog2 = (new BlogEntity())->assign([
            'id' => 'p2',
            'isCloseout' => true,
            'stock' => 0,
        ]);

        $blog3 = (new BlogEntity())->assign([
            'id' => 'p3',
            'isCloseout' => false,
            'stock' => 0,
        ]);

        $blogs = new BlogCollection([$blog1, $blog2, $blog3]);

        $processor = new TestAbstractBlogSliderProcessor();

        $filteredBlogs = $processor->publicFilterOutOutOfStockHiddenCloseoutBlogs($blogs);
        static::assertCount(2, $filteredBlogs);
        static::assertTrue($filteredBlogs->has('p1'));
        static::assertTrue($filteredBlogs->has('p3'));
    }
}

/**
 * @internal
 */
class TestAbstractBlogSliderProcessor extends AbstractBlogSliderProcessor
{
    public function getDecorated(): AbstractBlogSliderProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function getSource(): string
    {
        return 'test';
    }

    public function collect(CmsSlotEntity $slot, FieldConfigCollection $config, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ElementDataCollection $result, ResolverContext $resolverContext): void
    {
        // nth
    }

    public function publicFilterOutOutOfStockHiddenCloseoutBlogs(BlogCollection $blogs): BlogCollection
    {
        return $this->filterOutOutOfStockHiddenCloseoutBlogs($blogs);
    }
}
