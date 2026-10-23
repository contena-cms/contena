<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\TextStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Defaults;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(TextCmsElementResolver::class)]
class TextCmsElementResolverTest extends TestCase
{
    private TextCmsElementResolver $textResolver;

    protected function setUp(): void
    {
        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);
        $this->textResolver = new TextCmsElementResolver($sanitizer);
    }

    public function testType(): void
    {
        static::assertSame('text', $this->textResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = $this->createResolverContext();

        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->textResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testWithStaticContent(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, 'lorem ipsum dolor'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('lorem ipsum dolor', $textStruct->getContent());
    }

    public function testStaticContentIsDelegatedToSanitizer(): void
    {
        $contaminated = 'lorem<script>console.log("ipsum dolor")</script>';
        $sanitized = 'lorem';

        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturn($sanitized);
        $resolver = new TextCmsElementResolver($sanitizer);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, $contaminated));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($sanitized, $textStruct->getContent());
    }

    public function testWithMappedContent(): void
    {
        $blog = new BlogEntity();
        $blog->setDescription('foobar loo');

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'blog.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($blog->getDescription(), $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslationFallback(): void
    {
        $blog = new BlogEntity();
        $blog->setTranslated(['description' => 'fallback foo']);

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'blog.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('fallback foo', $textStruct->getContent());
    }

    public function testWithMappedContentAndTranslation(): void
    {
        $blog = new BlogEntity();
        $blog->setDescription('foobar loo');
        $blog->setTranslated(['description' => 'fallback foo']);

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'blog.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame($blog->getDescription(), $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedCustomFieldVariable(): void
    {
        $blog = $this->createBlogEntity();
        $blog->setCustomFields(['testField' => 'testing123']);

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ blog.customFields.testField }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title testing123</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariable(): void
    {
        $blog = $this->createBlogEntity();

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ blog.name }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title ' . $blog->getName() . '</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedVariableNotFound(): void
    {
        $blog = $this->createBlogEntity();

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ blog.unknownProperty }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Title {{ blog.unknownProperty }}</h1>', $textStruct->getContent());
    }

    public function testWithStaticContentAndNullValue(): void
    {
        $blog = $this->createBlogEntity();

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndEmptyValue(): void
    {
        $blog = $this->createBlogEntity();

        $resolverContext = $this->createResolverContextWithBlog($blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, ''));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('', $textStruct->getContent());
    }

    public function testWithStaticContentAndDateTimeValue(): void
    {
        $releaseDate = new \DateTime('2023-06-28T14:27:29');
        $blog = $this->createBlogEntity();
        $blog->setReleaseDate($releaseDate);
        $request = new Request();

        $resolverContext = new EntityResolverContext(static::createStub(ChannelContext::class), $request, new BlogDefinition(), $blog);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '{{ blog.releaseDate }}'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->textResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        $content = $textStruct->getContent();
        static::assertIsString($content);

        $formatter = new \IntlDateFormatter($request->getLocale(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
        $actualReleaseDate = new \DateTime();
        $actualReleaseDate->setTimestamp((int) $formatter->parse($content));

        static::assertSame($releaseDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $actualReleaseDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    private function createSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('text');
        $slot->setConfig([]);

        return $slot;
    }

    private function createResolverContextWithBlog(BlogEntity $blog): EntityResolverContext
    {
        return new EntityResolverContext(static::createStub(ChannelContext::class), new Request(), new BlogDefinition(), $blog);
    }

    private function createBlogEntity(): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->setName('TextBlog');

        return $blog;
    }

    private function createResolverContext(): ResolverContext
    {
        return new ResolverContext(static::createStub(ChannelContext::class), new Request());
    }
}
