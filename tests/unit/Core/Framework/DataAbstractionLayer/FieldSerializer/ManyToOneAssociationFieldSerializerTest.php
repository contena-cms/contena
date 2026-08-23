<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ManyToOneAssociationFieldSerializer::class)]
class ManyToOneAssociationFieldSerializerTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $payload
     */
    #[DataProvider('invalidArrayProvider')]
    public function testExceptionIsThrownIfDataIsNotAssociativeArray(array $payload): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::expectedAssociativeArray('/customer'));

        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $result = $serializer->encode(
            $field,
            static::createStub(EntityExistence::class),
            new KeyValuePair('customer', $payload, true),
            $params
        );

        iterator_to_array($result);
    }

    public function testExceptionInNormalizationIsThrownIfDataIsNotArray(): void
    {
        $this->expectExceptionObject(DataAbstractionLayerException::expectedArray('/0/customer'));

        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $serializer->normalize(
            $field,
            ['customer' => 'foobar'],
            $params,
        );
    }

    public static function invalidArrayProvider(): \Generator
    {
        yield [
            'payload' => ['should-be-an-associative-array'],
        ];

        yield [
            'payload' => [1 => 'apple', 'orange'],
        ];

        yield [
            'payload' => [0 => 'apple', 1 => 'orange'],
        ];

        yield [
            'payload' => [3 => 'apple', 5 => 'orange'],
        ];
    }

    public function testCanEncodeAssociativeArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $id = Uuid::randomHex();

        $result = $serializer->encode(
            $field,
            static::createStub(EntityExistence::class),
            new KeyValuePair('customer', ['id' => $id, 'name' => 'Jimmy'], true),
            $params
        );

        static::assertSame([], iterator_to_array($result));
    }
}

/**
 * @internal
 */
class OrderDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'order';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name')->addFlags(new Required()),
            new FkField('customer_id', 'customerId', CustomerDefinition::class),

            new ManyToOneAssociationField(
                'customer',
                'customer_id',
                CustomerDefinition::class,
                'id',
            ),
        ]);
    }
}

/**
 * @internal
 */
class CustomerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'customer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new Required(), new PrimaryKey()),
            new StringField('first_name', 'first_name')->addFlags(new Required()),
            new StringField('last_name', 'last_name')->addFlags(new Required()),

            new OneToManyAssociationField(
                'orders',
                OrderDefinition::class,
                'customer_id',
            ),
        ]);
    }
}
