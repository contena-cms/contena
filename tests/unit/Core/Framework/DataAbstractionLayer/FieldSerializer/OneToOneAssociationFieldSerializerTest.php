<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(OneToOneAssociationFieldSerializer::class)]
class OneToOneAssociationFieldSerializerTest extends TestCase
{
    public function testExceptionInNormalizationIsThrownIfDataIsNotArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                TestUserDefinition::class => $userDefinition = new TestUserDefinition(),
                UserRecoveryDefinition::class => new UserRecoveryDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $userDefinition->getField('recoveryUser');

        static::assertInstanceOf(OneToOneAssociationField::class, $field);

        $serializer = new OneToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $userDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::expectedArray('/0/recoveryUser'));

        $serializer->normalize(
            $field,
            ['recoveryUser' => 'foobar'],
            $params,
        );
    }

    public function testExceptionInEncodeIsThrownIfDataIsNotArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                TestUserDefinition::class => $userDefinition = new TestUserDefinition(),
                UserRecoveryDefinition::class => new UserRecoveryDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $userDefinition->getField('recoveryUser');

        static::assertInstanceOf(OneToOneAssociationField::class, $field);

        $serializer = new OneToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $userDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::expectedArray('/0/recoveryUser'));

        $serializer->encode(
            $field,
            static::createStub(EntityExistence::class),
            new KeyValuePair('recoveryUser', 'foobar', false),
            $params,
        )->next();
    }
}

/**
 * @internal
 */
class UserRecoveryDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'user_recovery';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('hash', 'hash'))->addFlags(new Required()),
            (new FkField('user_id', 'userId', TestUserDefinition::class))->addFlags(new Required()),

            new OneToOneAssociationField(
                'user',
                'user_id',
                'id',
                TestUserDefinition::class,
                false
            ),
        ]);
    }
}

/**
 * @internal
 */
class TestUserDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'user';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('first_name', 'first_name'))->addFlags(new Required()),
            (new StringField('last_name', 'last_name'))->addFlags(new Required()),

            new OneToOneAssociationField(
                'recoveryUser',
                'id',
                'user_id',
                UserRecoveryDefinition::class,
                false
            ),
        ]);
    }
}
