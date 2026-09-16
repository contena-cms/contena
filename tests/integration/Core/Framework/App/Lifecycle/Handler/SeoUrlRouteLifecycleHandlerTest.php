<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Contena\Core\Defaults;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Contena\Core\Framework\App\Lifecycle\Handler\SeoUrlRouteLifecycleHandler;
use Contena\Core\Framework\App\Manifest\Xml\Frontend\SeoUrl;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Framework\App\AppFixture;
use Contena\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class SeoUrlRouteLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const IMPRINT_ROUTE = 'frontend.app.CtSeoUrlApp.imprint';
    private const TEASER_ROUTE = 'frontend.app.CtSeoUrlApp.blog-teaser';

    private SeoUrlRouteLifecycleHandler $handler;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(SeoUrlRouteLifecycleHandler::class);
        $this->connection = static::getContainer()->get(Connection::class);

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testInstallPersistsTheDeclaredRoutesAndSeedsTheDefaultTemplate(): void
    {
        $manifest = $this->createManifest();
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        $routes = $this->fetchRoutes($app->getId());
        static::assertSame(['blog-teaser', 'imprint'], array_keys($routes));

        static::assertSame(self::IMPRINT_ROUTE, $routes['imprint']['route_name']);
        static::assertSame('imprint', $routes['imprint']['hook']);
        static::assertNull($routes['imprint']['entity_name']);
        static::assertNull($routes['imprint']['default_template']);
        static::assertSame(['en-GB' => 'ct-imprint'], json_decode((string) $routes['imprint']['paths'], true));
        static::assertSame(['en-GB' => 'Imprint'], json_decode((string) $routes['imprint']['label'], true));

        static::assertSame(self::TEASER_ROUTE, $routes['blog-teaser']['route_name']);
        static::assertSame('blog-teaser', $routes['blog-teaser']['hook']);
        static::assertSame('blog', $routes['blog-teaser']['entity_name']);
        static::assertSame('{{ blog.translated.name }}', $routes['blog-teaser']['default_template']);
        static::assertNull($routes['blog-teaser']['paths']);

        static::assertSame([
            'entityName' => 'blog',
            'template' => '{{ blog.translated.name }}',
            'isHeadless' => 0,
            'isValid' => 1,
        ], $this->fetchDefaultTemplate(self::TEASER_ROUTE));

        static::assertNull($this->fetchDefaultTemplate(self::IMPRINT_ROUTE));
    }

    public function testUpdateOverwritesADefaultTemplateThatWasNotChangedByTheMerchant(): void
    {
        [$app] = $this->install();

        $updated = $this->createManifest('{{ blog.translated.name }}/{{ blog.id }}');
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        $template = $this->fetchDefaultTemplate(self::TEASER_ROUTE);
        static::assertIsArray($template);
        static::assertSame('{{ blog.translated.name }}/{{ blog.id }}', $template['template']);

        $routes = $this->fetchRoutes($app->getId());
        static::assertSame('{{ blog.translated.name }}/{{ blog.id }}', $routes['blog-teaser']['default_template']);
    }

    public function testUpdateKeepsADefaultTemplateThatWasChangedByTheMerchant(): void
    {
        [$app] = $this->install();

        $this->connection->update(
            'seo_url_template',
            ['template' => 'teaser/{{ blog.id }}'],
            ['route_name' => self::TEASER_ROUTE]
        );

        $updated = $this->createManifest('{{ blog.translated.name }}/{{ blog.id }}');
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        $template = $this->fetchDefaultTemplate(self::TEASER_ROUTE);
        static::assertIsArray($template);
        static::assertSame('teaser/{{ blog.id }}', $template['template']);
    }

    public function testUpdateRemovesRoutesThatAreNoLongerDeclared(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);

        $updated = ManifestFixture::empty()->withName('CtSeoUrlApp')->withSeoUrl($this->teaserSeoUrl());
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        static::assertSame(['blog-teaser'], array_keys($this->fetchRoutes($app->getId())));
        static::assertSame([], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    public function testDeactivateMarksTheGeneratedSeoUrlsAsDeleted(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);
        $this->createSeoUrl(self::TEASER_ROUTE);

        $this->handler->deactivate(new AppActivationContext($app, Context::createDefaultContext()));

        static::assertSame([1], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertSame([1], $this->fetchSeoUrls(self::TEASER_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    public function testUninstallRemovesTheGeneratedSeoUrlsAndTheDefaultTemplate(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);
        $this->createSeoUrl(self::TEASER_ROUTE);

        $this->handler->uninstall(new AppRemovalContext($app, Context::createDefaultContext(), keepUserData: true));

        static::assertSame([], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertSame([], $this->fetchSeoUrls(self::TEASER_ROUTE));
        static::assertNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    /**
     * @return array{AppEntity}
     */
    private function install(): array
    {
        $manifest = $this->createManifest();
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        return [$app];
    }

    private function createManifest(string $template = '{{ blog.translated.name }}'): ManifestFixture
    {
        return ManifestFixture::empty()
            ->withName('CtSeoUrlApp')
            ->withSeoUrl(SeoUrl::fromArray([
                'name' => 'imprint',
                'label' => ['en-GB' => 'Imprint'],
                'path' => ['en-GB' => 'ct-imprint'],
            ]))
            ->withSeoUrl($this->teaserSeoUrl($template));
    }

    private function teaserSeoUrl(string $template = '{{ blog.translated.name }}'): SeoUrl
    {
        return SeoUrl::fromArray([
            'name' => 'blog-teaser',
            'entity' => 'blog',
            'label' => ['en-GB' => 'Blog teaser'],
            'defaultTemplate' => $template,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchRoutes(string $appId): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `name`, `route_name`, `hook`, `entity_name`, `default_template`, `paths`, `label`
             FROM `app_seo_url_route` WHERE `app_id` = :appId ORDER BY `name`',
            ['appId' => Uuid::fromHexToBytes($appId)]
        );
    }

    /**
     * @return array{entityName: string, template: string, isHeadless: int, isValid: int}|null
     */
    private function fetchDefaultTemplate(string $routeName): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `entity_name`, `template`, `is_headless`, `is_valid`
             FROM `seo_url_template` WHERE `route_name` = :routeName AND `channel_id` IS NULL',
            ['routeName' => $routeName]
        );

        if ($row === false) {
            return null;
        }

        return [
            'entityName' => (string) $row['entity_name'],
            'template' => (string) $row['template'],
            'isHeadless' => (int) $row['is_headless'],
            'isValid' => (int) $row['is_valid'],
        ];
    }

    /**
     * @return list<int>
     */
    private function fetchSeoUrls(string $routeName): array
    {
        return array_map(
            static fn (mixed $isDeleted): int => (int) $isDeleted,
            $this->connection->fetchFirstColumn(
                'SELECT `is_deleted` FROM `seo_url` WHERE `route_name` = :routeName',
                ['routeName' => $routeName]
            )
        );
    }

    private function createSeoUrl(string $routeName): void
    {
        $this->connection->insert('seo_url', [
            'data_scope_id' => Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE),
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => $routeName,
            'path_info' => '/frontend/script/test',
            'seo_path_info' => 'ct-' . Uuid::randomHex(),
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => new \DateTimeImmutable()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
