<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\BlogSlider;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;

/**
 * @internal
 */
class ContentStreamProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    private IdsCollection $ids;

    private ChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->createStreamAndBlogs();
    }

    public function testStreamSliderHidesOutOfStockCloseoutBlogsWhenSettingEnabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blogs = $this->resolveStreamSlider();

        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertTrue($blogs->has($this->ids->get('available-blog')));
        static::assertFalse(
            $blogs->has($this->ids->get('closeout-oos-blog')),
            'OOS closeout blog must not appear in stream slider when hideCloseoutBlogsWhenOutOfStock is enabled'
        );
    }

    public function testStreamSliderKeepsOutOfStockCloseoutBlogsWhenSettingDisabled(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $blogs = $this->resolveStreamSlider();

        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertTrue($blogs->has($this->ids->get('available-blog')));
        static::assertTrue($blogs->has($this->ids->get('closeout-oos-blog')));
    }

    public function testHiddenCloseoutBlogsDoNotConsumeSliderLimit(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        // stock ASC puts the OOS closeout blog first; with a limit of 1 it would
        // consume the only slot if filtering happened after the stream search.
        $blogs = $this->resolveStreamSlider(1, 'stock:ASC');

        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(1, $blogs);
        static::assertTrue(
            $blogs->has($this->ids->get('available-blog')),
            'Hidden closeout blogs must not consume slider limit slots'
        );
    }

    private function resolveStreamSlider(?int $limit = null, ?string $sorting = null): ?BlogCollection
    {
        $resolverContext = new ResolverContext($this->context, new Request());

        $config = new FieldConfig('blogs', 'content_stream', $this->ids->get('stream'));
        $configs = new FieldConfigCollection();
        $configs->add($config);

        if ($limit !== null) {
            $configs->add(new FieldConfig('contentStreamLimit', FieldConfig::SOURCE_STATIC, $limit));
        }

        if ($sorting !== null) {
            $configs->add(new FieldConfig('contentStreamSorting', FieldConfig::SOURCE_STATIC, $sorting));
        }

        $slot = new CmsSlotEntity();
        $slot->setId(Uuid::randomHex());
        $slot->setType('blog-slider');
        $slot->setSlot('blogSlider');
        $slot->setFieldConfig($configs);
        $slot->setBlockId(Uuid::randomHex());

        $slots = new CmsSlotCollection();
        $slots->add($slot);

        $resolver = static::getContainer()->get(CmsSlotsDataResolver::class);
        $result = $resolver->resolve($slots, $resolverContext);

        $data = $result->first()?->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $data);

        return $data->getBlogs();
    }

    private function createStreamAndBlogs(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $this->ids->get('stream'),
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $context);

        $blogs = [
            (new BlogBuilder($this->ids, 'available-blog'))
                ->price(100)
                ->visibility()
                ->stock(10)
                ->closeout(false)
                ->build(),
            (new BlogBuilder($this->ids, 'closeout-oos-blog'))
                ->price(100)
                ->visibility()
                ->stock(0)
                ->closeout(true)
                ->build(),
        ];

        static::getContainer()->get('blog.repository')->create($blogs, $context);

        // Blogs created via BlogBuilder get their own tax; the ChannelContext
        // needs to know those taxes so price calculation during slider resolution does
        // not fail with "Could not find tax with id ...".
        foreach ($blogs as $blog) {
            if (isset($blog['tax'])) {
                $this->addTaxDataToChannel($this->context, $blog['tax']);
            }
        }
    }
}
