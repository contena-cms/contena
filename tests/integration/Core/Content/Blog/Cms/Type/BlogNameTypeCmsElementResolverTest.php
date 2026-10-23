<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms\Type;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Cms\BlogNameCmsElementResolver;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\TextStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogNameTypeCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BlogNameCmsElementResolver $blogNameCmsElementResolver;

    protected function setUp(): void
    {
        $this->blogNameCmsElementResolver = static::getContainer()->get(BlogNameCmsElementResolver::class);
    }

    public function testType(): void
    {
        static::assertSame('blog-name', $this->blogNameCmsElementResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-name');

        $collection = $this->blogNameCmsElementResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutContext(): void
    {
        $resolverContext = new ResolverContext(static::createStub(ChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-name');

        $this->blogNameCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testEnrichEntityResolverContext(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->setId('blog_01');
        $blog->setName('Blog 01');
        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::getContainer()->get(ChannelBlogDefinition::class), $blog);
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-name');

        $slot->setFieldConfig(new FieldConfigCollection([new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'blog.name')]));

        $this->blogNameCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('Blog 01', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariable(): void
    {
        $category = new CategoryEntity();
        $category->setName('TextCategory');

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(CategoryDefinition::class), $category);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ category.name }}</h1>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->blogNameCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title ' . $category->getName() . '</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariableNotFound(): void
    {
        $category = new CategoryEntity();
        $category->setName('TextCategory');

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), static::createStub(CategoryDefinition::class), $category);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ category.unknownProperty }}</h1>'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);
        $slot->setFieldConfig($fieldConfig);

        $this->blogNameCmsElementResolver->enrich($slot, $resolverContext, $result);

        /** @var TextStruct|null $textStruct */
        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title {{ category.unknownProperty }}</h1>', $textStruct->getContent());
    }
}
