<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlIndexer;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AppSeoUrlTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AppSystemTestBehaviour;
    use BasicTestDataBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SessionTestBehaviour;
    use FrontendControllerTestBehaviour;

    private const APP_NAME = 'SwagFrontendSeoUrl';

    private const IMPRINT_ROUTE = 'frontend.app.SwagFrontendSeoUrl.imprint';

    private const BLOG_ROUTE = 'frontend.app.SwagFrontendSeoUrl.app-blog';

    private const BLOG_REVIEWS_ROUTE = 'frontend.app.SwagFrontendSeoUrl.blog-reviews';

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testActivatingTheAppWritesTheStaticSeoUrlAndSeedsTheEntityTemplate(): void
    {
        $this->installApp();

        $rows = $this->fetchSeoUrls(self::IMPRINT_ROUTE, $this->getChannelId());
        static::assertCount(1, $rows);
        static::assertSame([
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/frontend/script/imprint',
            'seoPathInfo' => 'imprint',
            'isCanonical' => 1,
            'isModified' => 1,
            'isDeleted' => 0,
        ], $this->withoutIds($rows[0]));

        static::assertSame(
            ['blog', 'app-blog/{{ blog.blogNumber }}'],
            $this->fetchDefaultTemplate(self::BLOG_ROUTE)
        );
        static::assertNull($this->fetchDefaultTemplate(self::IMPRINT_ROUTE));
    }

    public function testInstallingTheAppStoresOneAppFeaturePerDeclaredSeoUrl(): void
    {
        $this->installApp();

        static::assertSame([
            ['type' => 'frontend_entity_seo_url', 'name' => 'app-blog'],
            ['type' => 'frontend_seo_url', 'name' => 'imprint'],
        ], $this->connection->fetchAllAssociative(
            'SELECT `type`, `name` FROM `app_feature` WHERE `app_id` = :appId ORDER BY `type`, `name`',
            ['appId' => Uuid::fromHexToBytes($this->loadApp()->getId())]
        ));
    }

    public function testTheEntityRouteIsOnlyKnownToTheRegistryWhileTheAppIsActive(): void
    {
        $this->installApp();

        $registry = static::getContainer()->get(SeoUrlRouteRegistry::class);

        $route = $registry->findByRouteName(self::BLOG_ROUTE);
        static::assertNotNull($route);
        static::assertSame('frontend.script_endpoint', $route->getConfig()->getTargetRouteName());
        static::assertSame('blog', $route->getConfig()->getDefinition()->getEntityName());

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        static::assertNull($registry->findByRouteName(self::BLOG_ROUTE));
    }

    public function testWritingAnEntityGeneratesItsCanonicalSeoUrl(): void
    {
        $this->installApp();
        $ids = $this->createBlog();

        $rows = $this->fetchSeoUrls(self::BLOG_ROUTE, $this->getChannelId());
        static::assertCount(1, $rows);
        static::assertSame([
            'foreignKey' => $ids->get('app-blog-1'),
            'pathInfo' => '/frontend/script/app-blog?id=' . $ids->get('app-blog-1'),
            'seoPathInfo' => 'app-blog/app-blog-1',
            'isCanonical' => 1,
            'isModified' => 0,
            'isDeleted' => 0,
        ], $this->withoutIds($rows[0]));
    }

    public function testWritingAnEntityWithIndexingDisabledDoesNotGenerateItsSeoUrl(): void
    {
        $this->installApp();

        $this->context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $this->createBlog();
        $this->runWorker();

        static::assertSame([], $this->fetchSeoUrls(self::BLOG_ROUTE, $this->getChannelId()));
    }

    public function testWritingAnEntityWithQueuedIndexingGeneratesItsSeoUrlOnlyInTheWorker(): void
    {
        $this->installApp();

        $this->context->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);
        $this->createBlog();

        static::assertSame([], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));

        $this->runWorker();

        static::assertSame(['app-blog/app-blog-1'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
    }

    public function testAFullIndexRunOfTheAppSeoUrlIndexerRegeneratesRemovedSeoUrls(): void
    {
        $this->installApp();
        $this->createBlog();
        $this->connection->executeStatement(
            'DELETE FROM `seo_url` WHERE `route_name` = :routeName',
            ['routeName' => self::BLOG_ROUTE]
        );
        $this->connection->executeStatement(
            'UPDATE `seo_url` SET `is_deleted` = 1 WHERE `route_name` = :routeName',
            ['routeName' => self::IMPRINT_ROUTE]
        );

        static::assertSame([], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));

        static::getContainer()->get(EntityIndexerRegistry::class)->index(useQueue: false, only: [AppSeoUrlIndexer::NAME]);
        $this->runWorker();

        static::assertSame(['app-blog/app-blog-1'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));
    }

    public function testChangingTheTemplateOfTheRouteRegeneratesTheSeoUrlWithTheNewTemplate(): void
    {
        $this->installApp();
        $this->createBlog();

        $this->changeDefaultTemplate(self::BLOG_ROUTE, 'blogs/{{ blog.blogNumber }}');
        $this->runWorker();

        static::assertSame(['blogs/app-blog-1'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
    }

    public function testEditingTheCanonicalUrlOfTheAppRouteKeepsTheAppRoute(): void
    {
        $channelId = $this->getChannelId();
        $this->installApp();
        $blogId = $this->createBlog(visibleIn: $channelId)->get('app-blog-1');
        $this->runWorker();

        $detailPageSeoUrls = $this->fetchSeoUrls(BlogPageSeoUrlRoute::ROUTE_NAME, $channelId);
        static::assertContains($blogId, array_column($detailPageSeoUrls, 'foreignKey'));

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'routeName' => self::BLOG_ROUTE,
            'foreignKey' => $blogId,
            'channelId' => $channelId,
            'pathInfo' => '/frontend/script/app-blog?id=' . $blogId,
            'seoPathInfo' => 'my-app-blog',
            'isCanonical' => true,
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        static::assertSame(['my-app-blog'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
        static::assertSame($detailPageSeoUrls, $this->fetchSeoUrls(BlogPageSeoUrlRoute::ROUTE_NAME, $channelId));
    }

    public function testTheFrontendServesTheGeneratedSeoUrlWithTheEntityIdInTheQuery(): void
    {
        $this->installApp();
        $ids = $this->createBlog();

        $response = $this->request('GET', 'app-blog/app-blog-1', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('app-blog', $body['page'] ?? null);
        static::assertSame($ids->get('app-blog-1'), $body['blogId'] ?? null);
    }

    public function testTheFrontendServesTheStaticSeoUrl(): void
    {
        $this->installApp();

        $response = $this->request('GET', 'imprint', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('imprint', $body['page'] ?? null);
    }

    public function testANewChannelDomainGetsTheStaticPathOfItsLocaleOnTheNextFullIndexRun(): void
    {
        $this->installApp();

        $channelId = $this->getChannelId();
        $germanId = $this->getDeDeLanguageId();

        static::getContainer()->get('channel_domain.repository')->create([[
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'languageId' => $germanId,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            'url' => 'http://localhost/swag-seo-url-app-de',
        ]], $this->context);
        $this->runWorker();

        static::assertSame(
            [Defaults::LANGUAGE_SYSTEM => 'imprint'],
            $this->fetchSeoPathsByLanguage(self::IMPRINT_ROUTE, $channelId)
        );

        static::getContainer()->get(EntityIndexerRegistry::class)->index(useQueue: false, only: [AppSeoUrlIndexer::NAME]);
        $this->runWorker();

        static::assertSame(
            [$germanId => 'impressum', Defaults::LANGUAGE_SYSTEM => 'imprint'],
            $this->fetchSeoPathsByLanguage(self::IMPRINT_ROUTE, $channelId)
        );
    }

    public function testInstallingAnotherAppThatDeclaresTheSameStaticPathFails(): void
    {
        $this->installApp();

        $this->expectExceptionObject(SeoException::appSeoUrlPathAlreadyRegistered('legal-notice', 'imprint', self::APP_NAME));

        $this->installAnotherApp('SwagLegalNotice');
    }

    public function testInstallingAnAppWhoseStaticPathIsTheCanonicalSeoUrlOfAnotherRouteFails(): void
    {
        /** @var EntityRepository<LandingPageCollection> $repository */
        $repository = static::getContainer()->get('landing_page.repository');
        $repository->create([[
            'name' => 'Imprint',
            'url' => 'imprint',
            'channels' => [['id' => $this->getChannelId()]],
        ]], $this->context);

        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths('frontend.landing.page'));

        $this->expectExceptionObject(SeoException::appSeoUrlPathInUse('legal-notice', 'imprint'));

        $this->installAnotherApp('SwagLegalNotice');
    }

    public function testInstallingAnotherAppThatDeclaresTheSameHookFails(): void
    {
        $this->installApp();

        $this->expectExceptionObject(SeoException::appSeoUrlHookAlreadyRegistered('other', 'app-blog', self::APP_NAME));

        $this->installAnotherApp('SwagHookThief');
    }

    public function testInstallingAnotherAppWithAHookOfItsOwnSucceeds(): void
    {
        $this->installApp();

        $this->installAnotherApp('own-hook/SwagHookThief');

        static::assertSame(
            ['blog', 'other/{{ blog.blogNumber }}'],
            $this->fetchDefaultTemplate('frontend.app.SwagHookThief.other')
        );
    }

    public function testInstallingAnAppThatMayNotReadTheEntityOfItsEntitySeoUrlFails(): void
    {
        $this->expectExceptionObject(SeoException::appEntitySeoUrlNotPermitted('user', 'user', ['user:read']));

        $this->installAnotherApp('SwagUserLeak');
    }

    public function testInstallingAnAppWhoseDefaultTemplateReadsAnAssociationItMayNotReadFails(): void
    {
        $this->expectExceptionObject(SeoException::appEntitySeoUrlNotPermitted('manufacturer-blog', 'blog', ['blog_manufacturer:read']));

        $this->installAnotherApp('SwagManufacturerLeak');
    }

    public function testUpdatingTheAppRemovesTheSeoUrlsAndTheTemplateOfARouteItNoLongerDeclares(): void
    {
        $this->installApp('previous-version/SwagFrontendSeoUrl');
        $this->createBlog();

        $this->updateApp('SwagFrontendSeoUrl');
        $this->runWorker();

        static::assertSame([1], array_values(array_unique($this->fetchDeletedFlags(self::BLOG_REVIEWS_ROUTE))));
        static::assertNull($this->fetchDefaultTemplate(self::BLOG_REVIEWS_ROUTE));

        static::assertSame(['app-blog/app-blog-1'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::BLOG_ROUTE));
        static::assertSame(['imprint'], $this->fetchCanonicalSeoPaths(self::IMPRINT_ROUTE));
    }

    public function testUpdatingAnEntitySeoUrlToAnotherEntityReplacesItsSeoUrlsAndResetsTheTemplate(): void
    {
        $this->installApp('previous-version/SwagFrontendSeoUrl');
        $ids = $this->createBlog();
        static::getContainer()->get('blog_review.repository')->create([[
            'blogId' => $ids->get('app-blog-1'),
            'channelId' => $this->getChannelId(),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'title' => 'excellent',
            'content' => 'Does what it says on the box',
            'externalUser' => 'Test',
            'points' => 5,
        ]], $this->context);
        $this->createChannelTemplate(self::BLOG_REVIEWS_ROUTE, 'blog', 'my-reviews/{{ blog.blogNumber }}');
        $this->runWorker();

        $this->updateApp('next-version/SwagFrontendSeoUrl');

        static::assertSame([1], array_values(array_unique($this->fetchDeletedFlags(self::BLOG_REVIEWS_ROUTE))));
        static::assertSame(
            [['channelId' => null, 'entityName' => 'blog_review', 'template' => 'blog-reviews/{{ blogReview.title }}']],
            $this->fetchTemplates(self::BLOG_REVIEWS_ROUTE)
        );

        $this->runWorker();

        static::assertSame(['blog-reviews/excellent'], $this->fetchCanonicalSeoPaths(self::BLOG_REVIEWS_ROUTE));
    }

    public function testChangingTheHookRetargetsTheSeoUrlsAndKeepsTheirPaths(): void
    {
        $channelId = $this->getChannelId();
        $this->installApp();
        $blogId = $this->createBlog()->get('app-blog-1');
        $this->runWorker();

        $this->getBrowser()->jsonRequest('PATCH', '/api/_action/seo-url/canonical', [
            'routeName' => self::BLOG_ROUTE,
            'foreignKey' => $blogId,
            'channelId' => $channelId,
            'pathInfo' => '/frontend/script/app-blog?id=' . $blogId,
            'seoPathInfo' => 'my-app-blog',
            'isCanonical' => true,
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->updateApp('hook-change/SwagFrontendSeoUrl');
        $this->runWorker();

        static::assertSame([[
            'foreignKey' => Uuid::fromStringToHex(self::IMPRINT_ROUTE),
            'pathInfo' => '/frontend/script/company-imprint',
            'seoPathInfo' => 'imprint',
            'isCanonical' => 1,
            'isModified' => 1,
            'isDeleted' => 0,
        ]], array_map($this->withoutIds(...), $this->fetchSeoUrls(self::IMPRINT_ROUTE, $channelId)));

        $blogPathInfo = '/frontend/script/blog-page?id=' . $blogId;
        static::assertSame([
            [
                'foreignKey' => $blogId,
                'pathInfo' => $blogPathInfo,
                'seoPathInfo' => 'app-blog/app-blog-1',
                'isCanonical' => null,
                'isModified' => 0,
                'isDeleted' => 0,
            ],
            [
                'foreignKey' => $blogId,
                'pathInfo' => $blogPathInfo,
                'seoPathInfo' => 'my-app-blog',
                'isCanonical' => 1,
                'isModified' => 1,
                'isDeleted' => 0,
            ],
        ], array_map($this->withoutIds(...), $this->fetchSeoUrls(self::BLOG_ROUTE, $channelId)));

        $response = $this->request('GET', 'my-app-blog', []);

        static::assertNotFalse($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $body = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertSame('blog-page', $body['page'] ?? null);
        static::assertSame($blogId, $body['blogId'] ?? null);
    }

    public function testDeactivatingTheAppMarksTheSeoUrlsAsDeleted(): void
    {
        $this->installApp();
        $this->createBlog();

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        $imprintFlags = $this->fetchDeletedFlags(self::IMPRINT_ROUTE);
        $blogFlags = $this->fetchDeletedFlags(self::BLOG_ROUTE);

        static::assertSame([1], array_values(array_unique($imprintFlags)));
        static::assertSame([1], array_values(array_unique($blogFlags)));
        static::assertNotNull($this->fetchDefaultTemplate(self::BLOG_ROUTE));
    }

    public function testUninstallingTheAppMarksTheSeoUrlsAsDeletedAndRemovesTheTemplate(): void
    {
        $this->installApp();
        $this->createBlog();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context);

        $imprintFlags = $this->fetchDeletedFlags(self::IMPRINT_ROUTE);
        $blogFlags = $this->fetchDeletedFlags(self::BLOG_ROUTE);

        static::assertSame([1], array_values(array_unique($imprintFlags)));
        static::assertSame([1], array_values(array_unique($blogFlags)));
        static::assertNull($this->fetchDefaultTemplate(self::BLOG_ROUTE));
    }

    public function testUninstallingTheAppWithKeepUserDataKeepsTheEditedTemplateForTheReinstallation(): void
    {
        $this->installApp();
        $this->createBlog();
        $this->changeDefaultTemplate(self::BLOG_ROUTE, 'merchant/{{ blog.blogNumber }}');
        $this->runWorker();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context, keepUserData: true);

        static::assertSame(['blog', 'merchant/{{ blog.blogNumber }}'], $this->fetchDefaultTemplate(self::BLOG_ROUTE));

        $this->installApp();

        static::assertSame(['blog', 'merchant/{{ blog.blogNumber }}'], $this->fetchDefaultTemplate(self::BLOG_ROUTE));
        static::assertSame(['merchant/app-blog-1'], $this->fetchCanonicalSeoPaths(self::BLOG_ROUTE));
    }

    public function testReinstallingWithKeptUserDataResetsEveryTemplateOfARouteTheNewVersionBindsToAnotherEntity(): void
    {
        $this->installApp('previous-version/SwagFrontendSeoUrl');
        $this->createChannelTemplate(self::BLOG_REVIEWS_ROUTE, 'blog', 'my-reviews/{{ blog.blogNumber }}');

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context, keepUserData: true);

        static::assertCount(2, $this->fetchTemplates(self::BLOG_REVIEWS_ROUTE));

        $this->installApp('next-version/SwagFrontendSeoUrl');

        static::assertSame(
            [['channelId' => null, 'entityName' => 'blog_review', 'template' => 'blog-reviews/{{ blogReview.title }}']],
            $this->fetchTemplates(self::BLOG_REVIEWS_ROUTE)
        );
    }

    private function installApp(string $fixture = 'SwagFrontendSeoUrl'): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/' . $fixture);

        $this->runWorker();
    }

    private function updateApp(string $fixture): void
    {
        static::getContainer()->get(AppManager::class)->update(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/' . $fixture . '/manifest.xml'),
            new AppUpdateParameters(),
            $this->loadApp(),
            $this->context
        );
    }

    private function installAnotherApp(string $fixture): void
    {
        static::getContainer()->get(AppManager::class)->install(
            Manifest::createFromXmlFile(__DIR__ . '/_fixtures/' . $fixture . '/manifest.xml'),
            new AppInstallParameters(),
            $this->context
        );
    }

    private function loadApp(): AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::APP_NAME));

        /** @var EntityRepository<AppCollection> $repository */
        $repository = static::getContainer()->get('app.repository');
        $app = $repository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(AppEntity::class, $app);

        return $app;
    }

    private function createBlog(?string $visibleIn = null): IdsCollection
    {
        $ids = new IdsCollection();

        $blog = (new BlogBuilder($ids, 'app-blog-1'))
            ->price(100)
            ->manufacturer('m1');

        if ($visibleIn !== null) {
            $blog->visibility($visibleIn);
        }

        /** @var EntityRepository<BlogCollection> $repository */
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([$blog->build()], $this->context);

        return $ids;
    }

    private function changeDefaultTemplate(string $routeName, string $template): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('channelId', null));

        /** @var EntityRepository<SeoUrlTemplateCollection> $repository */
        $repository = static::getContainer()->get('seo_url_template.repository');
        $templateId = $repository->searchIds($criteria, $this->context)->firstId();
        static::assertNotNull($templateId);

        $repository->update([['id' => $templateId, 'template' => $template]], $this->context);
    }

    private function createChannelTemplate(string $routeName, string $entityName, string $template): void
    {
        /** @var EntityRepository<SeoUrlTemplateCollection> $repository */
        $repository = static::getContainer()->get('seo_url_template.repository');
        $repository->create([[
            'channelId' => $this->getChannelId(),
            'routeName' => $routeName,
            'entityName' => $entityName,
            'template' => $template,
            'isValid' => true,
        ]], $this->context);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSeoUrls(string $routeName, string $channelId): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`language_id`)) AS `languageId`,
                    LOWER(HEX(`foreign_key`)) AS `foreignKey`,
                    `path_info` AS `pathInfo`,
                    `seo_path_info` AS `seoPathInfo`,
                    `is_canonical` AS `isCanonical`,
                    `is_modified` AS `isModified`,
                    `is_deleted` AS `isDeleted`
             FROM `seo_url`
             WHERE `route_name` = :routeName AND `channel_id` = :channelId
             ORDER BY `seo_path_info`',
            ['routeName' => $routeName, 'channelId' => Uuid::fromHexToBytes($channelId)]
        );

        return $rows;
    }

    /**
     * @return array<string, string> language id => SEO path
     */
    private function fetchSeoPathsByLanguage(string $routeName, string $channelId): array
    {
        $paths = [];

        foreach ($this->fetchSeoUrls($routeName, $channelId) as $row) {
            $paths[(string) $row['languageId']] = (string) $row['seoPathInfo'];
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function fetchCanonicalSeoPaths(string $routeName): array
    {
        /** @var list<string> $paths */
        $paths = $this->connection->fetchFirstColumn(
            'SELECT `seo_path_info` FROM `seo_url`
             WHERE `route_name` = :routeName AND `is_canonical` = 1 AND `is_deleted` = 0
             ORDER BY `seo_path_info`',
            ['routeName' => $routeName]
        );

        return $paths;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withoutIds(array $row): array
    {
        unset($row['languageId']);

        return array_map(
            static fn (mixed $value): mixed => \is_numeric($value) ? (int) $value : $value,
            $row
        );
    }

    /**
     * @return list<int>
     */
    private function fetchDeletedFlags(string $routeName): array
    {
        return array_map(
            static fn (mixed $isDeleted): int => (int) $isDeleted,
            $this->connection->fetchFirstColumn(
                'SELECT `is_deleted` FROM `seo_url` WHERE `route_name` = :routeName',
                ['routeName' => $routeName]
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchTemplates(string $routeName): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(`channel_id`)) AS `channelId`, `entity_name` AS `entityName`, `template`
             FROM `seo_url_template`
             WHERE `route_name` = :routeName
             ORDER BY `channel_id`',
            ['routeName' => $routeName]
        );

        return $rows;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function fetchDefaultTemplate(string $routeName): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `entity_name`, `template` FROM `seo_url_template`
             WHERE `route_name` = :routeName AND `channel_id` IS NULL',
            ['routeName' => $routeName]
        );

        if ($row === false) {
            return null;
        }

        return [(string) $row['entity_name'], (string) $row['template']];
    }
}
