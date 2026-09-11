<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;

    private const APP_NAME = 'SwagFrontendSeoUrl';

    private const IMPRINT_ROUTE = 'frontend.app.SwagFrontendSeoUrl.imprint';

    private const BLOG_ROUTE = 'frontend.app.SwagFrontendSeoUrl.app-blog';

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
            ['blog', 'app-blog/{{ blog.id }}'],
            $this->fetchDefaultTemplate(self::BLOG_ROUTE)
        );
        static::assertNull($this->fetchDefaultTemplate(self::IMPRINT_ROUTE));
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

    public function testANewChannelDomainGetsTheStaticPathOfItsLocale(): void
    {
        $this->installApp();

        $salesChannelId = $this->getChannelId();
        $germanId = $this->getDeDeLanguageId();

        static::getContainer()->get('channel_domain.repository')->create([[
            'id' => Uuid::randomHex(),
            'salesChannelId' => $salesChannelId,
            'languageId' => $germanId,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
            'url' => 'http://localhost/swag-seo-url-app-de',
        ]], $this->context);

        $rows = $this->fetchSeoUrls(self::IMPRINT_ROUTE, $salesChannelId);
        static::assertCount(2, $rows);

        $paths = [];
        foreach ($rows as $row) {
            $paths[(string) $row['languageId']] = $row['seoPathInfo'];
        }

        static::assertSame('imprint', $paths[Defaults::LANGUAGE_SYSTEM] ?? null);
        static::assertSame('impressum', $paths[$germanId] ?? null);
    }

    public function testDeactivatingTheAppMarksTheSeoUrlsAsDeleted(): void
    {
        $this->installApp();
        $this->createBlog();

        static::getContainer()->get(AppManager::class)->deactivate($this->loadApp(), $this->context);

        static::assertSame([1], $this->fetchDeletedFlags(self::IMPRINT_ROUTE));
        static::assertSame([1], $this->fetchDeletedFlags(self::BLOG_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::BLOG_ROUTE));
    }

    public function testUninstallingTheAppRemovesTheSeoUrlsAndTheTemplate(): void
    {
        $this->installApp();
        $this->createBlog();

        static::getContainer()->get(AppManager::class)->uninstall($this->loadApp(), $this->context);

        static::assertSame([], $this->fetchDeletedFlags(self::IMPRINT_ROUTE));
        static::assertSame([], $this->fetchDeletedFlags(self::BLOG_ROUTE));
        static::assertNull($this->fetchDefaultTemplate(self::BLOG_ROUTE));
    }

    private function installApp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures');
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

    private function createBlog(): IdsCollection
    {
        $ids = new IdsCollection();

        /** @var EntityRepository<BlogCollection> $repository */
        $repository = static::getContainer()->get('blog.repository');
        $repository->create([
            (new BlogBuilder($ids, 'app-blog-1'))
                ->build(),
        ], $this->context);

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSeoUrls(string $routeName, string $salesChannelId): array
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
             WHERE `route_name` = :routeName AND `channel_id` = :salesChannelId
             ORDER BY `seo_path_info`',
            ['routeName' => $routeName, 'salesChannelId' => Uuid::fromHexToBytes($salesChannelId)]
        );

        return $rows;
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
