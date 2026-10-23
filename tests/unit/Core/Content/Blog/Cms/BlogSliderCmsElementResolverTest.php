<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Blog\Cms\BlogSlider\AbstractBlogSliderProcessor;
use Contena\Core\Content\Blog\Cms\BlogSliderCmsElementResolver;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Tests\Unit\Core\Content\Blog\Cms\BlogSlider\BlogSliderFixture;

/**
 * @internal
 */
#[CoversClass(BlogSliderCmsElementResolver::class)]
class BlogSliderCmsElementResolverTest extends TestCase
{
    private FieldConfigCollection $config;

    private LoggerInterface&MockObject $logger;

    /**
     * @var AbstractBlogSliderProcessor[]
     */
    private array $processors = [];

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGetType(): void
    {
        $this->logger->expects($this->never())->method('error');

        static::assertSame('blog-slider', $this->getResolver()->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, null));

        $slot = BlogSliderFixture::getSlot($this->config);
        $collection = $this->getResolver()->collect($slot, BlogSliderFixture::getResolverContext());

        static::assertNull($collection);
    }

    public function testCollectNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects($this->once())->method('error')
            ->with('No blog slider processor found by provided source: "static"');

        $processor = $this->createMock(AbstractBlogSliderProcessor::class);
        $processor->expects($this->once())->method('getSource')->willReturn('not-existing-processor');
        $this->processors[] = $processor;

        $slot = BlogSliderFixture::getSlot($this->config);
        $collection = $this->getResolver()->collect($slot, BlogSliderFixture::getResolverContext());
        static::assertNull($collection);
    }

    public function testCollect(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $collection = new CriteriaCollection();
        $collection->add('blog', BlogDefinition::class, new Criteria());

        $processor = $this->createMock(AbstractBlogSliderProcessor::class);
        $processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $processor->expects($this->once())
            ->method('collect')
            ->willReturn($collection);

        $this->processors['static'] = $processor;

        $slot = BlogSliderFixture::getSlot($this->config);
        static::assertSame($collection, $this->getResolver()->collect($slot, BlogSliderFixture::getResolverContext()));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        // No processor is registered, so the resolver logs the missing "static" processor.
        $this->logger->expects($this->once())->method('error');

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, null));

        $slot = BlogSliderFixture::getSlot($this->config);
        $data = new ElementDataCollection();

        $processor = $this->createMock(AbstractBlogSliderProcessor::class);
        $processor->expects($this->never())->method('enrich');

        $this->getResolver()->enrich($slot, BlogSliderFixture::getResolverContext(), $data);
    }

    public function testEnrichNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects($this->once())->method('error')
            ->with('No blog slider processor found by provided source: "static"');

        $processor = $this->createMock(AbstractBlogSliderProcessor::class);
        $processor->expects($this->once())->method('getSource')->willReturn('not-existing-processor');
        $processor->expects($this->never())->method('enrich');
        $this->processors[] = $processor;

        $slot = BlogSliderFixture::getSlot($this->config);
        $data = new ElementDataCollection();

        $this->getResolver()->enrich($slot, BlogSliderFixture::getResolverContext(), $data);
    }

    public function testEnrich(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('blogs', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $slot = BlogSliderFixture::getSlot($this->config);
        $data = new ElementDataCollection();
        $resolverContext = BlogSliderFixture::getResolverContext();

        $processor = $this->createMock(AbstractBlogSliderProcessor::class);
        $processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $processor->expects($this->once())->method('enrich')->with($slot, $data, $resolverContext);

        $this->processors['static'] = $processor;

        $this->getResolver()->enrich($slot, BlogSliderFixture::getResolverContext(), $data);
    }

    private function getResolver(): BlogSliderCmsElementResolver
    {
        return new BlogSliderCmsElementResolver($this->processors, $this->logger);
    }
}
