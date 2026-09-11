<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteLoader;
use Contena\Frontend\Framework\Seo\App\AppStaticSeoUrlSynchronizer;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlLifecycleHandler::class)]
class AppSeoUrlLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const ROUTE_NAME = 'frontend.app.SwagSeoUrlApp.blog-teaser';

    /**
     * @var list<array<int, mixed>>
     */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->calls = [];
    }

    public function testActivateInvalidatesTheRouteCacheBeforeSynchronisingAndRegenerating(): void
    {
        $handler = $this->createHandler(
            [$this->entityRoute()],
            StaticEntityRepository::of(BlogCollection::class, [['id-1', 'id-2'], ['id-3'], []], $this->blogDefinition())
        );

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([
            ['invalidate'],
            ['sync', self::APP_ID],
            ['update', self::ROUTE_NAME, ['id-1', 'id-2']],
            ['update', self::ROUTE_NAME, ['id-3']],
        ], $this->calls);
    }

    public function testEntitiesAreRegeneratedInChunks(): void
    {
        $limits = [];

        $repository = StaticEntityRepository::of(
            BlogCollection::class,
            [
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return ['id-1'];
                },
                static function (Criteria $criteria) use (&$limits): array {
                    $limits[] = $criteria->getLimit();

                    return [];
                },
            ],
            $this->blogDefinition()
        );

        $this->createHandler([$this->entityRoute()], $repository)
            ->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([500, 500], $limits);
    }

    public function testUpdateRegeneratesTheSeoUrlsOfAnActiveApp(): void
    {
        $handler = $this->createHandler(
            [$this->entityRoute()],
            StaticEntityRepository::of(BlogCollection::class, [['id-1'], []], $this->blogDefinition())
        );

        $handler->update($this->persistContext(active: true));

        static::assertSame([
            ['invalidate'],
            ['sync', self::APP_ID],
            ['update', self::ROUTE_NAME, ['id-1']],
        ], $this->calls);
    }

    public function testUpdateSkipsAnInactiveApp(): void
    {
        $handler = $this->createHandler([$this->entityRoute()], StaticEntityRepository::of(BlogCollection::class, []));

        $handler->update($this->persistContext(active: false));

        static::assertSame([], $this->calls);
    }

    public function testRoutesBoundToAnUnknownEntityAreSkipped(): void
    {
        $handler = $this->createHandler(
            [[
                'appId' => self::APP_ID,
                'routeName' => 'frontend.app.SwagSeoUrlApp.blog-detail',
                'hook' => 'blog-detail',
                'entityName' => 'ce_blog',
                'defaultTemplate' => '{{ ceBlog.translated.title }}',
            ]],
            StaticEntityRepository::of(BlogCollection::class, [])
        );

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([['invalidate'], ['sync', self::APP_ID]], $this->calls);
    }

    public function testWithoutEntityRoutesOnlyTheStaticUrlsAreSynchronised(): void
    {
        $handler = $this->createHandler([], StaticEntityRepository::of(BlogCollection::class, []));

        $handler->activate(new AppActivationContext($this->app(), Context::createDefaultContext()));

        static::assertSame([['invalidate'], ['sync', self::APP_ID]], $this->calls);
    }

    /**
     * @param list<array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}> $entityRoutes
     * @param StaticEntityRepository<BlogCollection> $repository
     */
    private function createHandler(array $entityRoutes, EntityRepository $repository): AppSeoUrlLifecycleHandler
    {
        $loader = static::createStub(AppSeoUrlRouteLoader::class);
        $loader->method('invalidateCache')->willReturnCallback(function (): void {
            $this->calls[] = ['invalidate'];
        });
        $loader->method('getEntityRoutes')->willReturn($entityRoutes);

        $synchronizer = static::createStub(AppStaticSeoUrlSynchronizer::class);
        $synchronizer->method('sync')->willReturnCallback(function (?string $appId): void {
            $this->calls[] = ['sync', $appId];
        });

        $updater = static::createStub(SeoUrlUpdater::class);
        $updater->method('update')->willReturnCallback(function (string $routeName, array $ids): void {
            $this->calls[] = ['update', $routeName, $ids];
        });

        $container = new Container();
        $container->set('blog.repository', $repository);

        return new AppSeoUrlLifecycleHandler(
            $loader,
            $synchronizer,
            $updater,
            new DefinitionInstanceRegistry(
                $container,
                ['blog' => BlogDefinition::class],
                ['blog' => 'blog.repository']
            )
        );
    }

    private function persistContext(bool $active): AppPersistContext
    {
        return new AppPersistContext(
            manifest: static::createStub(Manifest::class),
            app: $this->app($active),
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
        );
    }

    private function app(bool $active = true): AppEntity
    {
        $app = new AppEntity();
        $app->setId(self::APP_ID);
        $app->setActive($active);

        return $app;
    }

    private function blogDefinition(): BlogDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );

        $definition = $registry->getByEntityName(BlogDefinition::ENTITY_NAME);
        static::assertInstanceOf(BlogDefinition::class, $definition);

        return $definition;
    }

    /**
     * @return array{appId: string, routeName: string, hook: string, entityName: string, defaultTemplate: string}
     */
    private function entityRoute(): array
    {
        return [
            'appId' => self::APP_ID,
            'routeName' => self::ROUTE_NAME,
            'hook' => 'blog-teaser',
            'entityName' => 'blog',
            'defaultTemplate' => '{{ blog.translated.name }}',
        ];
    }
}
