<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ManyToManyAssociationFieldSerializer::class)]
class ManyToManyAssociationFieldSerializerTest extends TestCase
{
    public function testExceptionIsThrownIfSubresourceNotArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                'Media' => $mediaDefinition = new MediaDefinition(),
                'MediaGallery' => new MediaGalleryDefinition(),
                'MediaGalleryMapping' => new MediaGalleryMappingDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $mediaDefinition->getField('galleries');

        static::assertInstanceOf(ManyToManyAssociationField::class, $field);

        $serializer = new ManyToManyAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $mediaDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(new ExpectedArrayException('/galleries/0'));

        $serializer->normalize($field, [
            'galleries' => [
                'should-be-an-array',
            ],
        ], $params);
    }

    public function testDecodeThrowsException(): void
    {
        $serializer = new ManyToManyAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $field = new ManyToManyAssociationField(
            'galleries',
            'MediaGallery',
            'MediaGalleryMapping',
            'media_id',
            'gallery_id',
        );

        $this->expectExceptionObject(DataAbstractionLayerException::decodeHandledByHydrator($field));

        $serializer->decode($field, []);
    }

    public function testNormalizeThrowsExceptionIfMappingDefinitionHasNoForeignKeys(): void
    {
        $mediaDefinition = new MediaDefinition();
        $mediaGalleryDefinition = new MediaGalleryDefinition();
        $mediaGalleryMappingDefinition = new MediaGalleryMappingDefinition();

        new StaticDefinitionInstanceRegistry(
            [
                'Media' => $mediaDefinition,
                'MediaGallery' => $mediaGalleryDefinition,
                'MediaGalleryMapping' => $mediaGalleryMappingDefinition,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $field = $mediaDefinition->getField('galleries');

        static::assertInstanceOf(ManyToManyAssociationField::class, $field);

        $serializer = new ManyToManyAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $mediaDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::foreignKeyNotFoundInDefinition('galleries', MediaGalleryMappingDefinition::class));
        $serializer->normalize($field, [
            'galleries' => [
                ['id' => 'gallery-id-1'],
                ['id' => 'gallery-id-2'],
            ],
        ], $params);
    }
}

/**
 * @internal
 */
class MediaDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'media';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new StringField('file_extension', 'fileExtension')->addFlags(new ApiAware()),
            new ManyToManyAssociationField(
                'galleries',
                'MediaGallery',
                'MediaGalleryMapping',
                'media_id',
                'gallery_id',
            ),
        ]);
    }
}

/**
 * @internal
 */
class MediaGalleryDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'media_gallery';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new Required(), new PrimaryKey()),
            new StringField('title', 'title')->addFlags(new Required()),

            new ManyToManyAssociationField(
                'media',
                'Media',
                'MediaGalleryMapping',
                'gallery_id',
                'media_id'
            ),
        ]);
    }
}

/**
 * @internal
 */
class MediaGalleryMappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'media_gallery_mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // No defined FK fields on purpose
            new ManyToOneAssociationField('media', 'media_id', 'Media', 'id'),
            new ManyToOneAssociationField('galleries', 'gallery_id', 'MediaGallery', 'id'),
        ]);
    }
}
