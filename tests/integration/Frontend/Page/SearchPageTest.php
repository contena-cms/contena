<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Page\Search\SearchPageLoadedEvent;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class SearchPageTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    private const string TEST_TERM = 'foo';

    public function testItDoesSearch(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);
        $context = $this->createChannelContext();
        $searchPageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $searchPageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertEmpty($page->getListing()->getEntities());
        static::assertSame(self::TEST_TERM, $page->getSearchTerm());
        self::assertPageEvent(SearchPageLoadedEvent::class, $searchPageLoadedEvent, $context, $request, $page);
    }

    public function testItDoesApplyDefaultSorting(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createChannelContext();

        $searchPageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $searchPageLoadedEvent);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(
            'score',
            $page->getListing()->getSorting()
        );
    }

    public function testItDisplaysCorrectTitle(): void
    {
        $request = new Request(['search' => self::TEST_TERM]);

        $context = $this->createChannelContext();

        $searchPageLoadedEvent = null;
        $this->catchEvent(SearchPageLoadedEvent::class, $searchPageLoadedEvent);

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.siteName', 'Contena', $context->getChannelId());

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Search results | Contena', $page->getMetaInformation()?->getMetaTitle());

        $systemConfig->set('core.basicInformation.siteName', 'Test site', $context->getChannelId());

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame('Search results | Test site', $page->getMetaInformation()?->getMetaTitle());
    }

    protected function getPageLoader(): SearchPageLoader
    {
        return static::getContainer()->get(SearchPageLoader::class);
    }
}
