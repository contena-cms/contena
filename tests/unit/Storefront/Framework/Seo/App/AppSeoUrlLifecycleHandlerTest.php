<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Contena\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Contena\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Contena\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Contena\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Contena\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Symfony\Component\Messenger\Envelope;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlLifecycleHandler::class)]
class AppSeoUrlLifecycleHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const APP_NAME = 'SwagSeoUrlApp';

    private const ROUTE_NAME_PREFIX = 'storefront.app.SwagSeoUrlApp.';

    private const PRODUCT_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const CATEGORY_TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.category-teaser';

    private const BLOG_DETAIL_ROUTE = 'storefront.app.SwagSeoUrlApp.blog-detail';

    private const TEMPLATE_ID = 'dddddddddddddddddddddddddddddddd';

    private const OTHER_TEMPLATE_ID = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    /**
     * @var StaticEntityRepository<SeoUrlTemplateCollection>
     */
    private StaticEntityRepository $seoUrlTemplateRepository;

    private CollectingMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->seoUrlTemplateRepository = StaticEntityRepository::of(SeoUrlTemplateCollection::class);
        $this->messageBus = new CollectingMessageBus();
    }

    /**
     * @param list<string> $declaredSeoUrls
     */
    #[DataProvider('entitySeoUrlsGoneFromTheManifest')]
    public function testUpdateDeletesTheTemplateOfAnEntitySeoUrlTheManifestNoLongerDeclares(array $declaredSeoUrls): void
    {
        $this->seoUrlTemplateRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals([new EqualsAnyFilter('routeName', [self::BLOG_DETAIL_ROUTE])], $criteria->getFilters());

            return [self::TEMPLATE_ID];
        });

        $this->handler(storedEntitySeoUrls: ['product-teaser' => 'product', 'blog-detail' => 'ce_blog'])
            ->update(self::updateContext(self::manifest(
                seoUrls: $declaredSeoUrls,
                entitySeoUrls: ['product-teaser' => 'product'],
            )));

        static::assertSame([[['id' => self::TEMPLATE_ID]]], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function entitySeoUrlsGoneFromTheManifest(): iterable
    {
        yield 'an entity SEO URL that is not declared anymore' => [[]];
        yield 'an entity SEO URL that turned into a static SEO URL' => [['blog-detail']];
    }

    /**
     * @param list<string> $storedSeoUrls
     * @param array<string, string> $storedEntitySeoUrls
     * @param array<string, string> $declaredEntitySeoUrls
     */
    #[DataProvider('routesStillDeclaredAsEntitySeoUrl')]
    public function testUpdateKeepsTheTemplatesOfARedeclaredRoute(
        array $storedSeoUrls,
        array $storedEntitySeoUrls,
        array $declaredEntitySeoUrls,
    ): void {
        $this->handler(storedSeoUrls: $storedSeoUrls, storedEntitySeoUrls: $storedEntitySeoUrls)
            ->update(self::updateContext(self::manifest(entitySeoUrls: $declaredEntitySeoUrls)));

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{list<string>, array<string, string>, array<string, string>}>
     */
    public static function routesStillDeclaredAsEntitySeoUrl(): iterable
    {
        yield 'an entity SEO URL now bound to another entity' => [
            [],
            ['category-teaser' => 'category'],
            ['category-teaser' => 'product'],
        ];
        yield 'a static SEO URL that turned into an entity SEO URL' => [
            ['imprint'],
            [],
            ['imprint' => 'product'],
        ];
    }

    public function testUpdateWithoutRemovedRoutesDeletesNoTemplates(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->update(self::updateContext(self::manifest(
            seoUrls: ['imprint', 'contact'],
            entitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
        )));

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    public function testUpdateLeavesTheRegenerationToTheAppUpdatedEvent(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->never())->method('syncStaticRoutes');

        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
            synchronizer: $synchronizer,
        )->update(self::updateContext(self::manifest(
            seoUrls: ['imprint'],
            entitySeoUrls: ['product-teaser' => 'product'],
        )));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testTheAppUpdatedEventTriggersTheRegeneration(): void
    {
        static::assertSame(
            [AppUpdatedEvent::class => 'regenerateUpdatedApp'],
            AppSeoUrlLifecycleHandler::getSubscribedEvents()
        );
    }

    public function testAnUpdatedActiveAppSynchronisesTheStaticSeoUrlsAndRebuildsEveryEntitySeoUrl(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(self::APP_ID);

        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
            synchronizer: $synchronizer,
        )->regenerateUpdatedApp(self::appUpdatedEvent(active: true));

        static::assertEquals(
            [
            ],
            $this->dispatchedMessages()
        );
    }

    public function testAnUpdatedActiveAppWithoutEntitySeoUrlsOnlySynchronisesItsStaticSeoUrls(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(self::APP_ID);

        $this->handler(storedSeoUrls: ['imprint'], synchronizer: $synchronizer)
            ->regenerateUpdatedApp(self::appUpdatedEvent(active: true));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testAnUpdatedInactiveAppRegeneratesNothing(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->never())->method('syncStaticRoutes');

        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
            synchronizer: $synchronizer,
        )->regenerateUpdatedApp(self::appUpdatedEvent(active: false));

        static::assertSame([], $this->dispatchedMessages());
    }

    public function testActivationSynchronisesTheStaticSeoUrlsAndRebuildsEveryEntitySeoUrlOfTheApp(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(self::APP_ID);

        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
            synchronizer: $synchronizer,
        )->activate(self::activationContext());

        static::assertEquals(
            [
            ],
            $this->dispatchedMessages()
        );
    }

    public function testDeactivationKeepsTheTemplatesAndRegeneratesNothing(): void
    {
        $this->handler(
            storedSeoUrls: ['imprint'],
            storedEntitySeoUrls: ['product-teaser' => 'product'],
        )->deactivate(self::activationContext());

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
        static::assertSame([], $this->dispatchedMessages());
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingTheAppDeletesTheTemplatesOfItsEntitySeoUrls(\Closure $removal): void
    {
        $this->seoUrlTemplateRepository->addSearch(static function (Criteria $criteria): array {
            static::assertEquals(
                [new EqualsAnyFilter('routeName', [self::PRODUCT_TEASER_ROUTE, self::CATEGORY_TEASER_ROUTE])],
                $criteria->getFilters()
            );

            return [self::TEMPLATE_ID, self::OTHER_TEMPLATE_ID];
        });

        $removal(
            $this->handler(
                storedSeoUrls: ['imprint'],
                storedEntitySeoUrls: ['product-teaser' => 'product', 'category-teaser' => 'category'],
            ),
            self::removalContext(keepUserData: false)
        );

        static::assertSame([[['id' => self::TEMPLATE_ID], ['id' => self::OTHER_TEMPLATE_ID]]], $this->seoUrlTemplateRepository->deletes);
        static::assertSame([], $this->dispatchedMessages());
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingTheAppWhileKeepingUserDataKeepsItsTemplates(\Closure $removal): void
    {
        $removal(
            $this->handler(storedEntitySeoUrls: ['product-teaser' => 'product']),
            self::removalContext(keepUserData: true)
        );

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @param \Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void $removal
     */
    #[DataProvider('removals')]
    public function testRemovingAnAppWithoutSeoUrlsDeletesNoTemplates(\Closure $removal): void
    {
        $removal($this->handler(), self::removalContext(keepUserData: false));

        static::assertSame([], $this->seoUrlTemplateRepository->deletes);
    }

    /**
     * @return iterable<string, array{\Closure(AppSeoUrlLifecycleHandler, AppRemovalContext): void}>
     */
    public static function removals(): iterable
    {
        yield 'uninstalling the app' => [
            static fn (AppSeoUrlLifecycleHandler $handler, AppRemovalContext $context) => $handler->uninstall($context),
        ];
        yield 'deleting the app without notifying its app server' => [
            static fn (AppSeoUrlLifecycleHandler $handler, AppRemovalContext $context) => $handler->delete($context),
        ];
    }


    /**
     * @return list<object>
     */
    private function dispatchedMessages(): array
    {
        return array_values(array_map(
            static fn (Envelope $envelope): object => $envelope->getMessage(),
            $this->messageBus->getMessages()
        ));
    }




    private static function activationContext(): AppActivationContext
    {
        return new AppActivationContext(self::app(), Context::createDefaultContext());
    }

    private static function removalContext(bool $keepUserData = false): AppRemovalContext
    {
        return new AppRemovalContext(self::app(), Context::createDefaultContext(), $keepUserData);
    }

    private static function appUpdatedEvent(bool $active): AppUpdatedEvent
    {
        return new AppUpdatedEvent(self::app(active: $active), self::manifest(), Context::createDefaultContext());
    }

    private static function app(bool $active = true): AppEntity
    {
        return AppFixture::createAppEntity(self::APP_NAME, self::APP_ID, active: $active);
    }
}
