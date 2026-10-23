<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class CmsSlotsDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private ChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = $this->getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        EventHookDispatcher::fromContainer(static::getContainer())->subscribe(new CmsSlotsDataTestSubscriber());
        $this->initData();
    }

    public function testBlogSliderAcceptsCustomAssociations(): void
    {
        $resolverContext = new ResolverContext($this->context, new Request());

        $config = new FieldConfig('blogs', 'blog_stream', $this->ids->get('stream'));
        $configs = new FieldConfigCollection();
        $configs->add($config);

        $slot = new CmsSlotEntity();
        $slot->setId(Uuid::randomHex());
        $slot->setType('blog-slider');
        $slot->setSlot('blogSlider');
        $slot->setFieldConfig($configs);
        $slot->setBlockId(Uuid::randomHex());

        $slots = new CmsSlotCollection();
        $slots->add($slot);

        $blogSliderData = $this->getContainer()->get(CmsSlotsDataResolver::class)->resolve($slots, $resolverContext)->first()?->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $blogSliderData);

        $blog = $blogSliderData->getBlogs()?->get($this->ids->get('blog-1'));
        static::assertInstanceOf(BlogEntity::class, $blog);

        $tags = $blog->getTags();
        static::assertInstanceOf(TagCollection::class, $tags);
        static::assertCount(1, $tags);
    }

    private function initData(): void
    {
        $context = Context::createDefaultContext();
        $this->getContainer()->get('blog_stream.repository')->create([
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

        $taxIds = $this->context->getTaxRules()->getIds();
        $this->ids->set('t1', (string) array_pop($taxIds));

        $blogs = [
            (new BlogBuilder($this->ids, 'blog-1'))
                ->price(100)
                ->visibility()
                ->tag('tag-1')
                ->build(),
        ];

        $this->getContainer()->get('blog.repository')->create($blogs, $context);
    }
}

/**
 * @internal
 */
class CmsSlotsDataTestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CmsSlotsDataCollectExtension::NAME . '.post' => 'addAssociations',
        ];
    }

    public function addAssociations(CmsSlotsDataCollectExtension $extension): void
    {
        $collection = current($extension->result);
        \assert($collection instanceof CriteriaCollection);

        $list = $collection->all()[BlogDefinition::class] ?? null;
        \assert(\is_array($list));

        $criteria = current($list);
        \assert($criteria instanceof Criteria);

        $criteria->addAssociation('tags');
    }
}
