<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Adapter\Cache\Script;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ScriptCacheInvalidationSubscriberTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    /**
     * @param callable(ContainerInterface): void $closure
     */
    #[DataProvider('invalidationCasesProvider')]
    public function testCacheInvalidation(string $tagName, callable $closure, bool $shouldBeInvalidated, IdsCollection $ids): void
    {
        $this->createBlogs($ids);

        $this->loadAppsFromDir(__DIR__ . '/_fixtures/');

        $cache = static::getContainer()->get('cache.object');
        $item = $cache->getItem($tagName);
        $item->set('test');
        $item->tag($tagName);
        $cache->save($item);

        $closure(static::getContainer());

        static::assertSame(!$shouldBeInvalidated, $cache->hasItem($tagName));
    }

    public static function invalidationCasesProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'simple event' => [
            'my-tag',
            static function (ContainerInterface $container) use ($ids): void {
                $manufacturerRepo = $container->get('blog_manufacturer.repository');

                $manufacturerRepo->upsert([[
                    'id' => $ids->get('m1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'filter by entity name invalidates for correct entity' => [
            'my-manufacturer-' . $ids->get('m1'),
            static function (ContainerInterface $container) use ($ids): void {
                $manufacturerRepo = $container->get('blog_manufacturer.repository');

                $manufacturerRepo->upsert([[
                    'id' => $ids->get('m1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'filter by entity name does not invalidate for wrong entity' => [
            'my-manufacturer-' . $ids->get('m1'),
            static function (ContainerInterface $container) use ($ids): void {
                $blogRepo = $container->get('blog.repository');

                $blogRepo->upsert([[
                    'id' => $ids->get('p1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            false,
            $ids,
        ];

        yield 'complex filter correctly invalidates' => [
            'my-blog-' . $ids->get('v4.1'),
            static function (ContainerInterface $container) use ($ids): void {
                $blogRepo = $container->get('blog.repository');

                $blog4 = (new BlogBuilder($ids, 'p4'))
                    ->price(100)
                    ->variant(
                        (new BlogBuilder($ids, 'v4.1'))
                            ->build()
                    );

                $blogRepo->create([$blog4->build()], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'complex filter does not invalidate wrong operation' => [
            'my-blog-' . $ids->get('p2'),
            static function (ContainerInterface $container) use ($ids): void {
                $blogRepo = $container->get('blog.repository');

                $blogRepo->delete([['id' => $ids->get('p2')]], Context::createDefaultContext());
            },
            false,
            $ids,
        ];

        yield 'complex filter does not invalidate with missing payload' => [
            'my-blog-' . $ids->get('p4'),
            static function (ContainerInterface $container) use ($ids): void {
                $blogRepo = $container->get('blog.repository');

                $blog4 = (new BlogBuilder($ids, 'p4'))
                    ->price(100);

                $blogRepo->create([$blog4->build()], Context::createDefaultContext());
            },
            false,
            $ids,
        ];
    }

    private function createBlogs(IdsCollection $ids): void
    {
        $blog1 = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->variant(
                (new BlogBuilder($ids, 'v1.1'))
                    ->build()
            );

        $blog2 = (new BlogBuilder($ids, 'p2'))
            ->price(200)
            ->active(false);

        $blog3 = (new BlogBuilder($ids, 'p3'))
            ->price(300);

        static::getContainer()->get('blog.repository')->create([
            $blog1->build(),
            $blog2->build(),
            $blog3->build(),
        ], Context::createDefaultContext());
    }
}
