<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Page\LandingPage;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\LandingPage\LandingPageException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Frontend\Page\LandingPage\LandingPageLoadedEvent;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Test\Page\FrontendPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class LandingPageLoaderTest extends TestCase
{
    use FrontendPageTestBehaviour;
    use IntegrationTestBehaviour;

    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    private const string DEFAULT_LANDING_PAGE_ID = '43d1adaa1e699b09cb48643eadd87efb';

    public function testLoadWithoutId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));

        $this->getPageLoader()->load(new Request(), $this->createDefaultFrontendContext());
    }

    public function testLoad(): void
    {
        $request = new Request([], [], ['landingPageId' => self::DEFAULT_LANDING_PAGE_ID]);
        $context = $this->createDefaultFrontendContext();

        $event = null;
        $this->catchEvent(LandingPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(LandingPageEntity::class, $page->getLandingPage());
        static::assertSame(self::DEFAULT_LANDING_PAGE_ID, $page->getLandingPage()->getId());
        static::assertPageEvent(LandingPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testLoadWithInactiveLandingPage(): void
    {
        static::getContainer()->get('landing_page.repository')->update([[
            'id' => self::DEFAULT_LANDING_PAGE_ID,
            'active' => false,
        ]], Context::createDefaultContext());

        $request = new Request([], [], ['landingPageId' => self::DEFAULT_LANDING_PAGE_ID]);

        $this->expectExceptionObject(LandingPageException::notFound(self::DEFAULT_LANDING_PAGE_ID));

        $this->getPageLoader()->load($request, $this->createDefaultFrontendContext());
    }

    protected function getPageLoader(): LandingPageLoader
    {
        return static::getContainer()->get(LandingPageLoader::class);
    }

    private function createDefaultFrontendContext(): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            self::DEFAULT_WEB_CHANNEL_ID,
        );
    }
}
