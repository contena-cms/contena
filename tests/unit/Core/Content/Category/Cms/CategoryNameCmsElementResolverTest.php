<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Cms\CategoryNameCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\SalesChannel\Struct\TextStruct;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryNameCmsElementResolver::class)]
class CategoryNameCmsElementResolverTest extends TestCase
{

    protected function setUp(): void
    {
        $sanitizer = static::createStub(HtmlSanitizer::class);
        $sanitizer->method('sanitize')->willReturnArgument(0);
        $this->resolver = new CategoryNameCmsElementResolver($sanitizer);
    }

    public function testType(): void
    {
        static::assertSame('category-name', $this->resolver->getType());
    }

    public function testCollectReturnsNull(): void
    {
        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        static::assertNull($this->resolver->collect($slot, $this->createResolverContext()));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->resolver->enrich($slot, $this->createResolverContextWithCategory($this->createCategory()), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testEnrichWithMappedContent(): void
    {
        $category = $this->createCategory();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'category.name'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContextWithCategory($category), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('Newsletter', $textStruct->getContent());
    }

    public function testEnrichWithMappedContentTranslationFallback(): void
    {
        $category = new CategoryEntity();
        $category->setTranslated(['name' => 'Translated name']);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'category.name'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContextWithCategory($category), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('Translated name', $textStruct->getContent());
    }

    public function testEnrichWithStaticContent(): void
    {
        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Manual heading</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContext(), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Manual heading</h1>', $textStruct->getContent());
    }

    public function testEnrichWithStaticContentAndEntityContextResolvesPlaceholders(): void
    {
        $category = $this->createCategory();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>{{ category.name }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $this->createResolverContextWithCategory($category), new ElementDataCollection());

        $textStruct = $slot->getData();
        static::assertInstanceOf(TextStruct::class, $textStruct);
        static::assertSame('<h1>Newsletter</h1>', $textStruct->getContent());
    }


    private function createCategory(): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setName('Newsletter');

        return $category;
    }


}
