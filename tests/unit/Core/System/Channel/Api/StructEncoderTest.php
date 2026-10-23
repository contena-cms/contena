<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Api\ResponseFields;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Entity\DefinitionRegistryChain;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(StructEncoder::class)]
class StructEncoderTest extends TestCase
{
    /**
     * Regression test where the cheapest price and cheapest price container were exposed because the StructEncoder did not consider sales channel definitions
     */
    public function testCheapestPricesAreNotExposed(): void
    {
        $blog = new ChannelBlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCheapestPrice(
            (new CheapestPrice())->assign([
                'hasRange' => false,
                'variantId' => Uuid::randomHex(),
                'parentId' => Uuid::randomHex(),
                'ruleId' => Uuid::randomHex(),
                'purchase' => 1.0,
                'reference' => 1.0,
                'price' => new PriceCollection(),
            ])
        );

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayNotHasKey('cheapestPrice', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertSame('test', $encoded['name']);
    }

    public function testNoneMappedFieldsAreNotExposed(): void
    {
        $blog = new ExtendedBlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayNotHasKey('notExposed', $encoded);
        static::assertArrayHasKey('name', $encoded);
        static::assertSame('test', $encoded['name']);
    }

    public function testExtensionAreSupported(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $manufacturer = (new BlogManufacturerEntity())->assign(['name' => 'foo']);
        $blog->addExtension('exposedExtension', $manufacturer);
        $blog->addExtension('notExposedExtension', $manufacturer);
        $blog->setName('test');

        $blog->addExtension('foreignKeys', new ArrayStruct(['exposedFk' => 'exposed', 'notExposedFk' => 'not_exposed'], 'blog'));
        $blog->addExtension('search', new ArrayEntity(['score' => 2000]));

        $structEncoder = $this->createStructEncoder([ExtensionDefinition::class]);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayHasKey('extensions', $encoded);
        static::assertArrayHasKey('exposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('search', $encoded['extensions']);
        static::assertArrayNotHasKey('notExposedExtension', $encoded['extensions']);
        static::assertArrayHasKey('foreignKeys', $encoded['extensions']);

        static::assertArrayHasKey('score', $encoded['extensions']['search']);
        static::assertArrayHasKey('exposedFk', $encoded['extensions']['foreignKeys']);
        static::assertArrayNotHasKey('notExposedFk', $encoded['extensions']['foreignKeys']);
    }

    public function testPayloadProtection(): void
    {
        $cart = new Cart('test');

        $item = new LineItem('test', LineItem::BLOG_LINE_ITEM_TYPE, 'test');

        $item->setPayload(['not_protected' => 'test', 'protected' => 'test'], ['not_protected' => false, 'protected' => true]);

        $cart->add($item);

        $structEncoder = $this->createStructEncoder();

        $encoded = $structEncoder->encode($cart, new ResponseFields());

        static::assertArrayHasKey('lineItems', $encoded);
        static::assertArrayHasKey(0, $encoded['lineItems']);
        static::assertArrayHasKey('payload', $encoded['lineItems'][0]);
        static::assertIsArray($encoded['lineItems'][0]['payload']);
        static::assertArrayHasKey('not_protected', $encoded['lineItems'][0]['payload']);
        static::assertArrayNotHasKey('protected', $encoded['lineItems'][0]['payload']);
    }

    public function testCustomFieldsAreExposed(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCustomFields(['visible_1' => 'test', 'visible_2' => 'test']);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        $expectedCustomFields = [
            'visible_1' => 'test',
            'visible_2' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testCustomFieldsWithPriceValueBeforeScalarValueAreEncoded(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setCustomFields([
            'custom_price_field' => new PriceCollection(),
            'custom_text_field' => 'Example text',
        ]);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertSame('Example text', $encoded['customFields']['custom_text_field']);
    }

    public function testCustomFieldsWithPriceValueAreFiltered(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setCustomFields([
            'custom_price_field' => new PriceCollection(),
            'visible_field' => 'Visible text',
            'blocked_field' => 'Blocked text',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'blog',
                    'name' => 'blocked_field',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class], $connection);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertSame('Visible text', $encoded['customFields']['visible_field']);
        static::assertArrayNotHasKey('blocked_field', $encoded['customFields']);
    }

    public function testCustomFieldsFieldIsBlocked(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'blog',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class], $connection);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testCustomFieldsFieldIsBlockedInNestedArray(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCustomFields(['visible' => 'test', 'blocked' => 'test']);
        $blog->setTranslated(['customFields' => ['visible' => 'test', 'blocked' => 'test']]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'blog',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class], $connection);

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        static::assertArrayHasKey('customFields', $encoded);
        static::assertEquals($expectedCustomFields, $encoded['customFields']);
        static::assertEquals($expectedCustomFields, $encoded['translated']['customFields']);
    }

    public function testResetReloadsBlockedCustomFields(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setName('test');
        $blog->setCustomFields(['visible' => 'test', 'blocked' => 'test']);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->exactly(2))
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'entity_name' => 'blog',
                    'name' => 'blocked',
                ],
            ]);

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class], $connection);

        $expectedCustomFields = [
            'visible' => 'test',
        ];

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);

        $structEncoder->reset();

        $encoded = $structEncoder->encode($blog, new ResponseFields());

        static::assertArrayHasKey('customFields', $encoded);
        static::assertSame($expectedCustomFields, $encoded['customFields']);
    }

    public function testResponseFieldsEncodeIncludesCorrectly(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setId('1');
        $blog->setName('test');
        $blog->setEan('ean123');

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $responseFields = new ResponseFields(['blog' => ['id', 'name']]);

        $encoded = $structEncoder->encode($blog, $responseFields);

        $expected = [
            'name' => 'test',
            'id' => '1',
            'apiAlias' => 'blog',
        ];

        static::assertSame($expected, $encoded);
    }

    public function testResponseFieldsEncodeExcludesCorrectly(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setId('1');
        $blog->setName('test');
        $blog->setEan('ean123');

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $responseFields = new ResponseFields(excludes: ['blog' => ['name']]);

        $encoded = $structEncoder->encode($blog, $responseFields);

        static::assertArrayHasKey('id', $encoded);
        static::assertArrayHasKey('ean', $encoded);
        static::assertArrayNotHasKey('name', $encoded);
    }

    public function testResponseFieldsEncodeIncludesAndExcludesCorrectly(): void
    {
        $blog = new BlogEntity();
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $blog->setId('1');
        $blog->setName('test');
        $blog->setEan('ean123');

        $structEncoder = $this->createStructEncoder([ChannelBlogDefinition::class]);

        $responseFields = new ResponseFields(['blog' => ['id', 'name', 'ean']], ['blog' => ['name']]);

        $encoded = $structEncoder->encode($blog, $responseFields);

        $expected = [
            'ean' => 'ean123',
            'id' => '1',
            'apiAlias' => 'blog',
        ];

        static::assertSame($expected, $encoded);
    }

    /**
     * @param array<int|string, class-string<EntityDefinition>|EntityDefinition> $definitions
     */
    private function createStructEncoder(array $definitions = [], ?Connection $connection = null): StructEncoder
    {
        $registry = new StaticDefinitionInstanceRegistry(
            $definitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $serializer = new Serializer([new StructNormalizer()], [new JsonEncoder()]);

        $connection ??= static::createStub(Connection::class);

        return new StructEncoder($this->getChainRegistry($registry), $serializer, $connection);
    }

    private function getChainRegistry(StaticDefinitionInstanceRegistry $registry): DefinitionRegistryChain
    {
        $mock = static::createStub(ContainerInterface::class);

        return new DefinitionRegistryChain($registry, new ChannelDefinitionInstanceRegistry('', $mock, [], []));
    }
}

/**
 * @internal
 */
class ExtendedBlogEntity extends BlogEntity
{
    public string $notExposed = 'test';
}

/**
 * @internal
 */
class ExtensionDefinition extends BlogDefinition
{
    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new ManyToOneAssociationField('exposedExtension', 'my_extension_id', BlogManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new ManyToOneAssociationField('notExposedExtension', 'my_extension_id', BlogManufacturerDefinition::class))->addFlags(new Extension())
        );
        $fields->add(
            (new FkField('exposed_fk', 'exposedFk', BlogManufacturerDefinition::class))->addFlags(new Extension(), new ApiAware())
        );
        $fields->add(
            (new FkField('not_exposed_fk', 'notExposedFk', BlogManufacturerDefinition::class))->addFlags(new Extension())
        );

        return $fields;
    }
}
