<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentRoute;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogCommentRoute::class)]
class BlogCommentRouteTest extends TestCase
{
    public function testLoadRestrictsToBlogChannelAndRootComments(): void
    {
        $blogId = '9a5d9343c1aa4ec2b544dd77e7f398e8';
        $context = Generator::generateChannelContext();
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('search')->with(
            static::callback(static function (Criteria $criteria): bool {
                static::assertSame('blog-comment-route', $criteria->getTitle());
                static::assertCount(2, $criteria->getAssociations());

                return $criteria->getLimit() === null
                    && $criteria->getAssociation('children')->getSorting() !== [];
            }),
            $context->getContext(),
        )->willReturn(new EntitySearchResult(
            0,
            new BlogCommentCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        $route = new BlogCommentRoute(
            $repository,
            new StaticSystemConfigService([
                $context->getChannelId() => ['core.listing.showComments' => true],
            ]),
            static::createStub(CacheTagCollector::class),
        );

        $route->load($blogId, new Request(), $context, new Criteria());
    }

    public function testLoadRejectsDisabledComments(): void
    {
        $context = Generator::generateChannelContext();
        $route = new BlogCommentRoute(
            static::createStub(EntityRepository::class),
            new StaticSystemConfigService([
                $context->getChannelId() => ['core.listing.showComments' => false],
            ]),
            static::createStub(CacheTagCollector::class),
        );

        static::expectExceptionObject(BlogException::commentsNotActive());
        $route->load('9a5d9343c1aa4ec2b544dd77e7f398e8', new Request(), $context, new Criteria());
    }
}
