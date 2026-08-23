<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Page\Navigation\NavigationPageLoadedEvent;
use Contena\Frontend\Page\Navigation\NavigationPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class NavigationPageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    public function testItDoesLoadAPage(): void
    {
        $request = new Request();
        $context = $this->createChannelContext();

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CategoryEntity::class, $page->getCategory());
        static::assertPageEvent(NavigationPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDeniesAccessToInactiveCategoryPage(): void
    {
        $context = $this->createChannelContext();
        $repository = static::getContainer()->get('category.repository');

        $categoryId = $context->getChannel()->getNavigationCategoryId();

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
        $context = $this->createChannelContext();
        $seoUrlHandler = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $event = null;
        $this->catchEvent(NavigationPageLoadedEvent::class, $event);

        $metaInformation = $this->getPageLoader()->load($request, $context)->getMetaInformation();
        static::assertNotNull($metaInformation);
        $canonical = $metaInformation->getVars()['canonical'];

        $seoUrl = $seoUrlHandler->replace($canonical, $request->getHost(), $context);

        static::assertSame('/', $seoUrl);
    }

    protected function getPageLoader(): NavigationPageLoader
    {
        return static::getContainer()->get(NavigationPageLoader::class);
    }
}
