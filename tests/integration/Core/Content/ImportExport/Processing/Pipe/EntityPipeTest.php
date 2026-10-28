<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Processing\Pipe\EntityPipe;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Contena\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class EntityPipeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityPipe(): void
    {
        $entityPipe = new EntityPipe(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            static::getContainer()->get(SerializerRegistry::class),
            null,
            null,
            static::getContainer()->get(PrimaryKeyResolver::class)
        );

        $sourceEntity = ProductDefinition::ENTITY_NAME;
        $config = new Config([], ['sourceEntity' => $sourceEntity], []);
        $id = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => $id,
            'stock' => 101,
            'productNumber' => 'P101',
            'active' => true,
            'translations' => new ProductTranslationCollection([
            ]),
        ]);
        $product->setUniqueIdentifier($id);

        $result = iterator_to_array($entityPipe->in($config, $product->jsonSerialize()));
        static::assertInstanceOf(ProductTranslationCollection::class, $product->getTranslations());
        $first = $product->getTranslations()->first();
        static::assertInstanceOf(ProductTranslationEntity::class, $first);
        $translation = $first->getName();
        static::assertIsString($translation);

        static::assertSame($id, $result['id']);
        static::assertSame($translation, $result['translations']['DEFAULT']['name']);
        static::assertSame((string) $product->getStock(), $result['stock']);
        static::assertSame($product->getProductNumber(), $result['productNumber']);
        static::assertSame('1', $result['active']);

        $result = iterator_to_array($entityPipe->out($config, $result));

        static::assertSame($product->getId(), $result['id']);
        static::assertSame($translation, $result['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($product->getStock(), $result['stock']);
        static::assertSame($product->getProductNumber(), $result['productNumber']);
        static::assertSame($product->getActive(), $result['active']);
    }
}
