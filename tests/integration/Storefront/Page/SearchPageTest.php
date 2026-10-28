<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Storefront\Page\Search\SearchPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class SearchPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private const TEST_TERM = 'foo';

    public function testItDoesSearch(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);
        $context = $this->createSalesChannelContextWithNavigation();
        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertCount(0, $page->getListing()->getEntities());
        static::assertSame(self::TEST_TERM, $page->getSearchTerm());
        self::assertPageEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent, $context, $request, $page);
    }

    public function testItDoesApplyDefaultSorting(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createSalesChannelContextWithNavigation();

        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(
            'score',
            $page->getListing()->getSorting()
        );
    }

    public function testItDisplaysCorrectTitle(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createSalesChannelContextWithNavigation();

        $homePageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $homePageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Search results | Demostore', $page->getMetaInformation()?->getMetaTitle());

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.shopName', 'Teststore', $context->getSalesChannelId());

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Search results | Teststore', $page->getMetaInformation()?->getMetaTitle());
    }

}
