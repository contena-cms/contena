<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItDoesLoadAPage(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CategoryEntity::class, $page->getCategory());
        static::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDeniesAccessToInactiveCategoryPage(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $repository = static::getContainer()->get('category.repository');

        $categoryId = $context->getSalesChannel()->getNavigationCategoryId();

        $repository->update([[
            'id' => $categoryId,
            'active' => false,
        ]], $context->getContext());

        $request = new Request([], [], ['navigationId' => $categoryId]);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $this->expectException(CategoryNotFoundException::class);
        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesHaveCanonicalTag(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();
        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $meta = $metaInformation->getVars();
        $canonical = $meta['canonical'];

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame('/', $seoUrl);
    }

}
