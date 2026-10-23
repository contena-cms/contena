<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\AdminBlogStreamController;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AdminBlogStreamController::class)]
class AdminBlogStreamControllerTest extends TestCase
{
    private MockObject&RequestCriteriaBuilder $requestCriteriaBuilder;

    private Stub&ChannelContextServiceInterface $channelContextService;

    /**
     * @var MockObject&ChannelRepository<BlogCollection>
     */
    private MockObject&ChannelRepository $channelRepository;

    private Stub&BlogDefinition $blogDefinition;

    protected function setUp(): void
    {
        $this->blogDefinition = static::createStub(BlogDefinition::class);
        $this->channelRepository = $this->createMock(ChannelRepository::class);
        $this->channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $this->requestCriteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
    }

    public function testBlogStreamPreview(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminBlogStreamController(
            $this->blogDefinition,
            $this->channelRepository,
            $this->channelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->channelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, ChannelContext $context) {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_EXACT, $criteria->getTotalCountMode());
                static::assertTrue($criteria->hasAssociation('manufacturer'));
                static::assertTrue($criteria->hasAssociation('options'));
                static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(BlogAvailableFilter::class, $criteria->getFilters()[0]);
                static::assertCount(0, $criteria->getGroupFields());

                return new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->blogStreamPreview('channelId', new Request(), $context);
        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString(
            '{"extensions":[],"elements":[],"aggregations":[],"page":1,"limit":null,"entity":"blog","total":1,"states":[]}',
            $response->getContent()
        );
    }

    public function testBlogStreamPreviewAppliesGroupingWhenDisplayAsGroupRequested(): void
    {
        $context = Context::createDefaultContext();
        $controller = new AdminBlogStreamController(
            $this->blogDefinition,
            $this->channelRepository,
            $this->channelContextService,
            $this->requestCriteriaBuilder,
        );

        $this->requestCriteriaBuilder->expects($this->once())->method('handleRequest')->willReturn(new Criteria());

        $this->channelRepository->expects($this->once())->method('search')
            ->willReturnCallback(static function (Criteria $criteria, ChannelContext $context) {
                // grouping mirrors the frontend listing: group by displayGroup and drop the null group
                static::assertContainsEquals(new FieldGrouping('displayGroup'), $criteria->getGroupFields());
                static::assertContainsEquals(new NotEqualsFilter('displayGroup', null), $criteria->getFilters());

                return new EntitySearchResult(
                    'blog',
                    0,
                    new BlogCollection(),
                    null,
                    $criteria,
                    $context->getContext()
                );
            });

        $response = $controller->blogStreamPreview(
            'channelId',
            new Request(['displayAsGroup' => '1']),
            $context
        );

        static::assertNotFalse($response->getContent());
    }
}
