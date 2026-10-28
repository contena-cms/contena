<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\UsageData\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\UsageData\Services\ManyToManyAssociationService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Doctrine\FakeResultFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ManyToManyAssociationService::class)]
class ManyToManyAssociationServiceTest extends TestCase
{
    public function testGetMappingIdsForAssociationFields(): void
    {
        $ids = new IdsCollection();
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT `referenceColumn`, `localColumn` FROM `ManyToManyEntity` WHERE (`localColumn` IN (:ids)) AND (`product_version_id` = UNHEX(:versionId))',
                [
                    'ids' => [
                        Uuid::fromHexToBytes($ids->get('1')),
                        Uuid::fromHexToBytes($ids->get('2')),
                        Uuid::fromHexToBytes($ids->get('3')),
                    ],
                    'versionId' => Defaults::LIVE_VERSION,
                ]
            )
            ->willReturn(
                FakeResultFactory::createResult(
                    [
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-2')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('2')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('3')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                    ],
                    $connection,
                )
            );

        $service = new ManyToManyAssociationService($connection);

        $mappingDefinition = new MappingDefinition();
        $toManyDefinition = new ToManyDefinition();
        $registry = new StaticDefinitionInstanceRegistry(
            [$mappingDefinition, $toManyDefinition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $associationField = new ManyToManyAssociationField(
            'propertyName',
            ToManyDefinition::class,
            MappingDefinition::class,
            'localColumn',
            'referenceColumn',
        );
        $associationField->compile($registry);

        $result = $service->getMappingIdsForAssociationFields(
            [$associationField],
            [
                ['primaryKeyName' => $ids->get('1')],
                ['primaryKeyName' => $ids->get('2')],
                ['primaryKeyName' => $ids->get('3')],
            ],
            'primaryKeyName'
        );

        static::assertSame([
            'propertyName' => [
                Uuid::fromHexToBytes($ids->get('1')) => [
                    $ids->get('referenceColumn-1'),
                    $ids->get('referenceColumn-2'),
                ],
                Uuid::fromHexToBytes($ids->get('2')) => [
                    $ids->get('referenceColumn-1'),
                ],
                Uuid::fromHexToBytes($ids->get('3')) => [
                    $ids->get('referenceColumn-1'),
                ],
            ],
        ], $result);
    }
}

/**
 * @internal
 */
class MappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'ManyToManyEntity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey()),
            new ManyToManyAssociationField('manyToMany', MockEntityDefinition::class, ManyToManyMappingEntityDefinition::class, 'manyToMany', 'manyToMany'),
        ]);
    }
}

/**
 * @internal
 */
class ToManyDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'to_many_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
        ]);
    }
}
