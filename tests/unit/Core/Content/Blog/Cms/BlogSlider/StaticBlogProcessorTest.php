<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\BlogSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Cms\BlogSlider\StaticBlogProcessor;
use Contena\Core\Content\Blog\Events\BlogSliderStaticCriteriaEvent;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(StaticBlogProcessor::class)]
class StaticBlogProcessorTest extends TestCase
{
    private FieldConfigCollection $config;

    private SystemConfigService&MockObject $configService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->configService = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testGetDecorated(): void
    {
        $this->configService->expects($this->never())->method('getBool');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->expectException(DecorationPatternException::class);
        $this->getProcessor()->getDecorated();
    }

    public function testGetSource(): void
    {
        $this->configService->expects($this->never())->method('getBool');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        static::assertSame('static', $this->getProcessor()->getSource());
    }

    public function testCollect(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $expectedIds = ['blog-1', 'blog-2'];

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, $expectedIds);
        $this->config->add($config);

        $this->configService->expects($this->never())->method('getBool');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(BlogSliderStaticCriteriaEvent::class));

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $list = array_shift($list);
        $criteria = $list['blog-slider_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $ids = $criteria->getIds();
        static::assertSame($expectedIds, $ids);

        static::assertTrue($criteria->hasAssociation('options'));
        static::assertTrue($criteria->getAssociation('options')->hasAssociation('group'));
    }

    public function testCollectEventCanModifyCriteria(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, ['blog-1']);
        $this->config->add($config);

        $this->configService->expects($this->never())->method('getBool');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (BlogSliderStaticCriteriaEvent $event): BlogSliderStaticCriteriaEvent {
                $event->criteria->addAssociation('manufacturer');

                return $event;
            });

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $list = array_shift($list);
        $criteria = $list['blog-slider_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertTrue($criteria->hasAssociation('manufacturer'));
    }

    public function testEnrichWithAvailableBlogs(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->hideUnavailableBlogs(false);

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, ['blog-1', 'blog-2']));
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $blogs = BlogSliderFixture::getBlogs();
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);

        $data = new ElementDataCollection();
        $data->add('blog-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $data);

        $blogs = $data->getBlogs();
        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(2, $blogs);
        static::assertTrue($blogs->has('blog-1'));
        static::assertTrue($blogs->has('blog-2'));
    }

    public function testEnrichRestoresConfiguredBlogOrder(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->hideUnavailableBlogs(false);

        // Configure the slot with blogs in order [blog-2, blog-1]
        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, ['blog-2', 'blog-1']));
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        // Simulate the DB returning blogs in a different order (e.g. as merged from another slider)
        $blogs = BlogSliderFixture::getBlogs(); // returns [blog-1, blog-2]
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);
        $searchResult->assign(['criteria' => new Criteria(['blog-1', 'blog-2', 'blog-2', 'blog-1'])]);

        $data = new ElementDataCollection();
        $data->add('blog-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $enrichedData = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $enrichedData);

        $enrichedBlogs = $enrichedData->getBlogs();
        static::assertInstanceOf(BlogCollection::class, $enrichedBlogs);
        static::assertCount(2, $enrichedBlogs);

        $ids = array_values($enrichedBlogs->getIds());
        static::assertSame(['blog-2', 'blog-1'], $ids, 'Blogs must be in the configured slider order, not DB order');
    }

    public function testEnrichHideUnavailableBlogs(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->hideUnavailableBlogs(true);

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, ['blog-1', 'blog-2']));
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $blogs = BlogSliderFixture::getBlogs();
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);

        $data = new ElementDataCollection();
        $data->add('blog-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $data);

        $blogs = $data->getBlogs();
        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(1, $blogs);
        static::assertTrue($blogs->has('blog-1'));
        static::assertFalse($blogs->has('blog-2'));
    }

    public function testEnrichDoesNothingWithoutSearchResult(): void
    {
        $this->configService->expects($this->never())->method('getBool');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();
        $data = new ElementDataCollection();

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertNull($data);
    }

    public function testEnrichDoesNothingWithoutBlogs(): void
    {
        $this->configService->expects($this->never())->method('getBool');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $searchResult = new EntitySearchResult(
            'tax',
            2,
            new EntityCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data = new ElementDataCollection();
        $data->add('blog-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertNull($data);
    }

    private function getProcessor(): StaticBlogProcessor
    {
        return new StaticBlogProcessor($this->configService, $this->eventDispatcher);
    }

    private function hideUnavailableBlogs(bool $value): void
    {
        $this->configService->expects($this->once())->method('getBool')->willReturn($value);
    }
}
