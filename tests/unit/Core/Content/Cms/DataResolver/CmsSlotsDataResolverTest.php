<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Extension\CmsSlotsDataCollectExtension;
use Contena\Core\Content\Cms\Extension\CmsSlotsDataEnrichExtension;
use Contena\Core\Content\Cms\Extension\CmsSlotsDataResolveExtension;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CmsSlotsDataResolver::class)]
class CmsSlotsDataResolverTest extends TestCase
{
    private FormCmsElementResolver&MockObject $formResolver;

    private HtmlCmsElementResolver&MockObject $htmlResolver;

    private TextCmsElementResolver&MockObject $textResolver;

    private DefinitionInstanceRegistry&Stub $registry;

    /**
     * @var ChannelRepository<ChannelBlogCollection>&Stub
     */
    private ChannelRepository&Stub $blogRepository;

    private EventDispatcher&Stub $dispatcher;

    private ExtensionDispatcher $extensions;

    protected function setUp(): void
    {
        $this->formResolver = $this->createMock(FormCmsElementResolver::class);
        $this->htmlResolver = $this->createMock(HtmlCmsElementResolver::class);
        $this->textResolver = $this->createMock(TextCmsElementResolver::class);
        $this->registry = static::createStub(DefinitionInstanceRegistry::class);
        $this->blogRepository = static::createStub(ChannelRepository::class);
        $this->dispatcher = static::createStub(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($this->dispatcher);
    }

    public function testResolveCallsCollectedResolvers(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-2',
                'slot' => 'content',
                'type' => 'html',
            ]),
            (new CmsSlotEntity())->assign([
                'id' => 'slot-3',
                'slot' => 'right',
                'type' => 'invalid',
            ]),
        ]);

        $criteria = new Criteria(['id-1', 'id-2']);
        $criteria->addFilter(new EqualsFilter('config', null));

        $criteria2 = new Criteria(['id-3', 'id-4']);

        $collection = new CriteriaCollection();
        $collection->add('criteria-1', 'slot', $criteria);
        $collection->add('criteria-2', 'slot', $criteria2);

        $this->formResolver->method('collect')->willReturn($collection);

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->expects($this->once())->method('enrich');

        $this->htmlResolver->method('getType')->willReturn('html');
        $this->htmlResolver->expects($this->once())->method('enrich');

        $this->textResolver->method('getType')->willReturn('text');
        $this->textResolver->expects($this->never())->method('enrich');

        $context = Generator::generateChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $resolver = $this->getCmsSlotsDataResolver();

        // assertion in mocked resolver method calls
        $resolver->resolve($slots, $resolverContext);
    }

    public function testResolvePublishesExtensions(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
        ]);

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->expects($this->once())->method('enrich');
        $this->htmlResolver->expects($this->never())->method('enrich');
        $this->textResolver->expects($this->never())->method('enrich');

        $criteria = new Criteria(['id-1', 'id-2']);
        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('criteria-1', 'slot', $criteria);

        $this->formResolver->method('collect')->willReturn($criteriaCollection);

        $context = Generator::generateChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $dispatcher = $this->createMock(EventDispatcher::class);
        $this->extensions = new ExtensionDispatcher($dispatcher);
        $dispatcher
            // 3 extensions, each dispatched as pre- and post-event
            ->expects($this->exactly(6))
            ->method('dispatch')
            ->willReturnCallback(static function (Extension $extension) use ($slots, $resolverContext, $criteriaCollection) {
                switch (true) {
                    case $extension instanceof CmsSlotsDataResolveExtension:
                        static::assertSame($slots, $extension->slots);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertInstanceOf(CmsSlotCollection::class, $extension->result);
                            static::assertCount(1, $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataCollectExtension:
                        static::assertCount(1, $extension->slots);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertSame(['slot-1' => $criteriaCollection], $extension->result);
                        }

                        return $extension;
                    case $extension instanceof CmsSlotsDataEnrichExtension:
                        static::assertSame($slots, $extension->slots);
                        static::assertSame(['slot-1' => $criteriaCollection], $extension->criteriaList);
                        static::assertSame($resolverContext, $extension->resolverContext);

                        if ($extension->result) {
                            static::assertInstanceOf(CmsSlotCollection::class, $extension->result);
                            static::assertCount(1, $extension->result);
                        }

                        return $extension;
                    default:
                        static::fail('No expected event was dispatched');
                }
            });

        $this->getCmsSlotsDataResolver()->resolve($slots, $resolverContext);
    }

    public function testResolveFiltersMergedDirectReadsPerSlot(): void
    {
        $slots = new CmsSlotCollection([
            (new CmsSlotEntity())->assign([
                'id' => 'slot-1',
                'slot' => 'left',
                'type' => 'form',
            ]),
        ]);

        $collection = new CriteriaCollection();
        $collection->add('criteria-1', BlogDefinition::class, new Criteria(['id-1']));

        $this->formResolver->method('getType')->willReturn('form');
        $this->formResolver->method('collect')->willReturn($collection);
        $this->htmlResolver->expects($this->never())->method('enrich');
        $this->textResolver->expects($this->never())->method('enrich');

        $context = Generator::generateChannelContext();
        $resolverContext = new ResolverContext($context, new Request());

        $wanted = (new ChannelBlogEntity())->assign(['id' => 'id-1']);
        $other = (new ChannelBlogEntity())->assign(['id' => 'id-2']);
        $wanted->internalSetEntityData('blog', new FieldVisibility([]));
        $other->internalSetEntityData('blog', new FieldVisibility([]));

        // the merged direct read fetches the ids of all slots at once, each slot only gets its own ids back
        $this->blogRepository->method('search')->willReturn(new EntitySearchResult(
            'blog',
            2,
            new ChannelBlogCollection([$wanted, $other]),
            null,
            new Criteria(['id-1', 'id-2']),
            $context->getContext(),
        ));

        $this->formResolver->expects($this->once())->method('enrich')
            ->willReturnCallback(static function (CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $data) use ($wanted): void {
                $filtered = $data->get('criteria-1');

                static::assertInstanceOf(EntitySearchResult::class, $filtered);
                static::assertSame(1, $filtered->getTotal());
                static::assertInstanceOf(ChannelBlogCollection::class, $filtered->getEntities());
                static::assertSame(['id-1' => $wanted], $filtered->getEntities()->getElements());
            });

        $blogDefinition = new BlogDefinition();
        $blogDefinition->compile($this->registry);
        $this->registry->method('get')->willReturn($blogDefinition);

        $resolver = new CmsSlotsDataResolver(
            [$this->formResolver, $this->htmlResolver, $this->textResolver],
            ['blog' => $this->blogRepository],
            $this->registry,
            $this->extensions,
        );

        $resolver->resolve($slots, $resolverContext);
    }

    private function getCmsSlotsDataResolver(): CmsSlotsDataResolver
    {
        $this->blogRepository->method('search')
            ->willReturn(static::createStub(EntitySearchResult::class));

        $blogDefinition = new BlogDefinition();
        $blogDefinition->compile($this->registry);

        $this->registry->method('get')->willReturn($blogDefinition);

        return new CmsSlotsDataResolver(
            [$this->formResolver, $this->htmlResolver, $this->textResolver],
            ['blog' => $this->blogRepository],
            $this->registry,
            $this->extensions,
        );
    }
}
