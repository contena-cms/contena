<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Sitemap\Provider\LandingPageUrlProvider;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class LandingPageUrlProviderTest extends TestCase
{
    use FrontendChannelTestHelper;
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    private LandingPageUrlProvider $landingPageUrlProvider;

    /**
     * @var EntityRepository<LandingPageCollection>
     */
    private EntityRepository $landingPageRepository;

    protected function setUp(): void
    {
        if (!static::getContainer()->has(BlogPageSeoUrlRoute::class)) {
            static::markTestSkipped('NEXT-16799: Sitemap module has a dependency on frontend routes');
        }

        $this->landingPageRepository = static::getContainer()->get('landing_page.repository');

        $this->channelContext = $this->createFrontendChannelContext(
            Uuid::randomHex(),
            'test-landing-pages-sitemap',
        );

        $this->landingPageUrlProvider = static::getContainer()->get(LandingPageUrlProvider::class);
    }

    public function testLandingPageUrlIsCorrect(): void
    {
        $this->createLandingPages();

        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 20);

        static::assertCount(10, $urlResult->getUrls());

        $invalidUrl = array_filter($urlResult->getUrls(), static function (Url $url) {
            return \in_array($url->getLoc(), [
                '/landing-page-11',
                '/landing-page-12',
                '/landing-page-13',
            ], true);
        });

        static::assertCount(0, $invalidUrl);

        [$firstUrl] = $urlResult->getUrls();

        static::assertSame('daily', $firstUrl->getChangefreq());
        static::assertSame(0.5, $firstUrl->getPriority());
        static::assertSame(LandingPageEntity::class, $firstUrl->getResource());
        static::assertTrue(Uuid::isValid($firstUrl->getIdentifier()));
    }

    public function testExcludedUrlsAreNotReturned(): void
    {
        $excludedId = Uuid::randomHex();

        $configHandler = $this->createMock(ConfigHandler::class);
        $configHandler->method('get')->with(ConfigHandler::EXCLUDED_URLS_KEY)->willReturn([
            [
                'resource' => LandingPageEntity::class,
                'channelId' => $this->channelContext->getChannelId(),
                'identifier' => $excludedId,
            ],
        ]);

        $this->landingPageRepository->upsert([
            [
                'id' => $excludedId,
                'name' => 'Landing page 1',
                'url' => 'landing-page-1',
                'active' => true,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ],
            [
                'name' => 'Landing page 2',
                'url' => 'landing-page-2',
                'active' => true,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ],
        ], $this->channelContext->getContext());

        $landingPageUrlProvider = new LandingPageUrlProvider(
            $configHandler,
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(EntityRouteResolver::class),
            static::getContainer()->get('event_dispatcher'),
        );

        $urlResult = $landingPageUrlProvider->getUrls($this->channelContext, 20);

        static::assertCount(1, $urlResult->getUrls());
        static::assertSame('landing-page-2', $urlResult->getUrls()[0]->getLoc());
    }

    public function testNoSeoPathInfo(): void
    {
        $id = Uuid::randomHex();

        $this->landingPageRepository->upsert([
            [
                'id' => $id,
                'name' => 'Landing page 1',
                'url' => 'landing-page-1',
                'active' => true,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ],
        ], $this->channelContext->getContext());

        // we delete the seo url to test the fallback
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $id));

        /** @var EntityRepository<SeoUrlCollection> $seuUrlRepository */
        $seuUrlRepository = static::getContainer()->get('seo_url.repository');

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $seuUrlRepository->search($criteria, $this->channelContext->getContext())->getEntities()->first();

        static::assertNotNull($seoUrl);

        $seuUrlRepository->delete([
            [
                'id' => $seoUrl->getId(),
            ],
        ], $this->channelContext->getContext());

        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 20);
        [$firstUrl] = $urlResult->getUrls();

        static::assertCount(1, $urlResult->getUrls());
        static::assertSame('/landingPage/' . $id, $firstUrl->getLoc());

        // we add a custom seo url
        $seuUrlRepository->upsert([
            [
                'routeName' => 'frontend.landing.page',
                'foreignKey' => $id,
                'pathInfo' => '/landingPage/1',
                'seoPathInfo' => 'seo-landing-page-1',
                'channelId' => $this->channelContext->getChannelId(),
                'isCanonical' => true,
                'isModified' => true,
            ],
        ], $this->channelContext->getContext());

        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 20);
        [$firstUrl] = $urlResult->getUrls();

        static::assertCount(1, $urlResult->getUrls());
        static::assertSame('seo-landing-page-1', $firstUrl->getLoc());
    }

    public function testReturnedOffsetIsCorrect(): void
    {
        $this->createLandingPages();

        // first run
        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 3);
        static::assertCount(3, $urlResult->getUrls());
        static::assertSame(3, $urlResult->getNextOffset());

        // 1+n run
        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 2, $urlResult->getNextOffset());
        static::assertCount(2, $urlResult->getUrls());
        static::assertSame(5, $urlResult->getNextOffset());

        // last run
        $urlResult = $this->landingPageUrlProvider->getUrls($this->channelContext, 100, $urlResult->getNextOffset()); // test with high number to get last chunk
        static::assertNull($urlResult->getNextOffset());
    }

    private function createLandingPages(): void
    {
        $validLandingPages = [];
        // add valid landing pages
        for ($i = 1; $i <= 10; ++$i) {
            $validLandingPages[] = [
                'name' => 'Landing page ' . $i,
                'url' => 'landing-page-' . $i,
                'active' => true,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ];
        }

        $this->landingPageRepository->upsert($validLandingPages, $this->channelContext->getContext());

        $newChannelContext = $this->createFrontendChannelContext(
            Uuid::randomHex(),
            'new-landing-pages-sitemap',
        );

        // add invalid landing pages
        $this->landingPageRepository->upsert([
            // different channel
            [
                'name' => 'Landing page 11',
                'url' => 'landing-page-11',
                'active' => true,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $newChannelContext->getChannelId()],
                ],
            ],
            // not active
            [
                'name' => 'Landing page 12',
                'url' => 'landing-page-12',
                'active' => false,
                'versionId' => Defaults::LIVE_VERSION,
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ],
            // not live version
            [
                'name' => 'Landing page 13',
                'url' => 'landing-page-13',
                'active' => true,
                'versionId' => Uuid::randomHex(),
                'channels' => [
                    ['id' => $this->channelContext->getChannelId()],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
