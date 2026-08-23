<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSearchRoute::class)]
class BlogSearchRouteTest extends TestCase
{
    /**
     * @var BlogSearchBuilderInterface&MockObject
     */
    private BlogSearchBuilderInterface $searchBuilder;

    /**
     * @var ChannelRepository<BlogCollection>&MockObject
     */
    private ChannelRepository $blogRepository;

    protected function setUp(): void
    {
        $this->searchBuilder = $this->createMock(BlogSearchBuilderInterface::class);
        $this->blogRepository = $this->createMock(ChannelRepository::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->getBlogSearchRoute()->getDecorated();
    }

    public function testLoadBuildsAndReturnsBlogListing(): void
    {
        $request = new Request(['search' => 'content']);
        $criteria = new Criteria();
        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());
        $context->method('getChannelId')->willReturn('channel-id');

        $this->searchBuilder->expects($this->once())
            ->method('build')
            ->with($request, $criteria, $context);
        $this->blogRepository->expects($this->once())
            ->method('search')
            ->with($criteria, $context)
            ->willReturn(new EntitySearchResult(
                0,
                new BlogCollection(),
                new AggregationResultCollection(),
                $criteria,
                Context::createDefaultContext()
            ));

        $response = $this->getBlogSearchRoute()->load($request, $context, $criteria);

        static::assertSame(0, $response->getListingResult()->getTotal());
        static::assertTrue($criteria->hasState(BlogSearchRoute::STATE));
        static::assertSame('blog-search-route', $criteria->getTitle());
        static::assertSame('blog_listing', $response->getListingResult()->getApiAlias());
    }

    private function getBlogSearchRoute(): BlogSearchRoute
    {
        return new BlogSearchRoute(
            $this->blogRepository,
            $this->searchBuilder,
            static::createStub(EventDispatcherInterface::class),
            new CompositeListingProcessor([]),
        );
    }
}
