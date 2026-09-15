<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentLoader;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentRoute;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentRouteResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogCommentLoader::class)]
class BlogCommentLoaderTest extends TestCase
{
    public function testItLoadsCommentsWithPagination(): void
    {
        $context = Generator::generateChannelContext();
        $route = $this->createMock(BlogCommentRoute::class);
        $route->expects($this->once())->method('load')->with(
            '9a5d9343c1aa4ec2b544dd77e7f398e8',
            static::isInstanceOf(Request::class),
            $context,
            static::callback(static fn (Criteria $criteria): bool => $criteria->getLimit() === 10 && $criteria->getOffset() === 10),
        )->willReturn(new BlogCommentRouteResponse(new EntitySearchResult(
            0,
            new BlogCommentCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        )));

        $loader = new BlogCommentLoader(
            $route,
            new StaticSystemConfigService([
                $context->getChannelId() => ['core.listing.commentsPerPage' => 10],
            ]),
            static::createStub(EventDispatcherInterface::class),
        );

        $result = $loader->load(new Request(['p' => 2]), $context, '9a5d9343c1aa4ec2b544dd77e7f398e8');

        static::assertSame('9a5d9343c1aa4ec2b544dd77e7f398e8', $result->getBlogId());
        static::assertCount(0, $result->getComments()->getEntities());
    }
}
