<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Frontend\Page\Blog\BlogPage;

/**
 * @internal
 */
#[CoversClass(BlogPage::class)]
class BlogPageTest extends TestCase
{
    public function testBlogPage(): void
    {
        $page = new BlogPage();
        $blog = new ChannelBlogEntity();
        $breadcrumb = new BreadcrumbCollection();

        $page->setBlog($blog);
        $page->setNavigationId('navigation-id');
        $page->setBreadcrumb($breadcrumb);

        static::assertSame(BlogDefinition::ENTITY_NAME, $page->getEntityName());
        static::assertSame($blog, $page->getBlog());
        static::assertSame('navigation-id', $page->getNavigationId());
        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }
}
