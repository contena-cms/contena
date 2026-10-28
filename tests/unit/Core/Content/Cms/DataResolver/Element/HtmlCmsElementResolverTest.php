<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\SalesChannel\Struct\HtmlStruct;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(HtmlCmsElementResolver::class)]
class HtmlCmsElementResolverTest extends TestCase
{
    private HtmlCmsElementResolver $htmlResolver;

    protected function setUp(): void
    {
        $this->htmlResolver = new HtmlCmsElementResolver();
    }

    public function testGetType(): void
    {
        static::assertSame('html', $this->htmlResolver->getType());
    }

    public function testCollect(): void
    {
        static::assertNull($this->htmlResolver->collect(
            new CmsSlotEntity(),
            $this->createResolverContext()
        ));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $slot = $this->createSlot();
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->htmlResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(HtmlStruct::class, $textStruct);
        static::assertNull($textStruct->getContent());
    }

    public function testWithMappedContent(): void
    {
        $product = new ProductEntity();
        $product->setDescription('foobar loo');

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_MAPPED, 'product.description'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->htmlResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(HtmlStruct::class, $textStruct);
        static::assertSame($product->getDescription(), $textStruct->getContent());
    }

    public function testWithStaticContent(): void
    {
        $resolverContext = $this->createResolverContext();
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>lorem ipsum dolor</h1><script>console.log("foo")</script>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->htmlResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(HtmlStruct::class, $textStruct);
        static::assertSame('<h1>lorem ipsum dolor</h1><script>console.log("foo")</script>', $textStruct->getContent());
    }

    public function testWithStaticContentAndMappedCustomFieldVariable(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['testField' => 'testing123']);

        $resolverContext = $this->createResolverContextWithProduct($product);
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('content', FieldConfig::SOURCE_STATIC, '<h1>Title {{ product.customFields.testField }}</h1>'));

        $slot = $this->createSlot();
        $slot->setFieldConfig($fieldConfig);

        $this->htmlResolver->enrich($slot, $resolverContext, $result);

        $textStruct = $slot->getData();
        static::assertInstanceOf(HtmlStruct::class, $textStruct);
        static::assertSame('<h1>Title testing123</h1>', $textStruct->getContent());
    }

    private function createResolverContext(): ResolverContext
    {
        return new ResolverContext(static::createStub(SalesChannelContext::class), new Request());
    }

    private function createResolverContextWithProduct(ProductEntity $product): EntityResolverContext
    {
        return new EntityResolverContext(static::createStub(SalesChannelContext::class), new Request(), new ProductDefinition(), $product);
    }

    private function createSlot(): CmsSlotEntity
    {
        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier(Uuid::randomHex());
        $slot->setType('html');
        $slot->setConfig([]);

        return $slot;
    }
}
