<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Navigation;

use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\AbstractCategoryRoute;
use Contena\Core\Content\Category\Channel\CategoryRouteResponse;
use Contena\Core\Content\Category\Channel\ChannelCategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\Navigation\NavigationPage;
use Contena\Frontend\Page\Navigation\NavigationPageLoader;
use Contena\Frontend\Page\Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationPageLoader::class)]
class NavigationPageLoaderTest extends TestCase
{
    public function testItTakesTheBreadcrumbFromTheRoute(): void
    {
        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', Uuid::randomHex())]);

        $category = new ChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);
        $category->setSeoBreadcrumb($breadcrumb);

        // the route already resolved it, and that is also what registers the path cache tags on this page
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = $this->load($category, $breadcrumbBuilder);

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItFallsBackToTheBuilderForADecoratedRoute(): void
    {
        // CategoryRouteResponse only guarantees a CategoryEntity, which cannot carry the breadcrumb
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $category->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $page = $this->load($category, $breadcrumbBuilder);

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    public function testItFallsBackWhenTheRouteLeftTheBreadcrumbUnset(): void
    {
        // the entity can carry it, but a decorated route may simply not populate it
        $category = new ChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setActive(true);

        $breadcrumb = new BreadcrumbCollection([new Breadcrumb('Home', $category->getId())]);

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->once())
            ->method('getCategoryBreadcrumbUrls')
            ->willReturn($breadcrumb);

        $page = $this->load($category, $breadcrumbBuilder);

        static::assertSame($breadcrumb, $page->getBreadcrumb());
    }

    private function load(CategoryEntity $category, CategoryBreadcrumbBuilder $breadcrumbBuilder): NavigationPage
    {
        $context = Generator::generateChannelContext();

        $request = new Request();
        $request->attributes->set('navigationId', $category->getId());

        $categoryRoute = static::createStub(AbstractCategoryRoute::class);
        $categoryRoute->method('load')->willReturn(new CategoryRouteResponse($category));

        $genericLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericLoader->method('load')->willReturn(new Page());

        $loader = new NavigationPageLoader(
            $genericLoader,
            new EventDispatcher(),
            $categoryRoute,
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $breadcrumbBuilder,
        );

        return $loader->load($request, $context);
    }
}
