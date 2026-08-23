<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(UpdatedByFieldSerializer::class)]
class UpdatedByFieldSerializerTest extends TestCase
{
    private DefinitionInstanceRegistry&Stub $definitionInstanceRegistry;

    private ValidatorInterface&Stub $validator;

    private UpdatedByFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->validator = static::createStub(ValidatorInterface::class);

        $this->fieldSerializer = new UpdatedByFieldSerializer(
            $this->validator,
            $this->definitionInstanceRegistry,
        );
    }

    public function testEncode(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $userId = Uuid::randomHex();

        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext($userId),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame($userId, Uuid::fromBytesToHex($result['updated_by_id'] ?? ''));
    }

    public function testEncodeWithInvalidField(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $wrongField = new JsonField('key', 'key');

        $this->expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(UpdatedByField::class, $wrongField));

        $this->fieldSerializer->encode(
            $wrongField,
            $existence,
            $data,
            $parameters
        )->current();
    }

    public function testEncodeWithoutExistingEntity(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(false);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(Uuid::randomHex()),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertEmpty($result);
    }

    public function testEncodeWithInvalidScope(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);

        $result = 'foo';
        Context::createDefaultContext()->scope('invalid-scope', function (Context $context) use ($data, $existence, &$result): void {
            $result = $this->fieldSerializer->encode(
                new UpdatedByField([Context::USER_SCOPE]),
                $existence,
                $data,
                new WriteParameterBag(
                    $this->createStub(EntityDefinition::class),
                    WriteContext::createFromContext($context),
                    '/',
                    new WriteCommandQueue(),
                )
            )->current();
        });

        static::assertNull($result);
    }

    public function testEncodeWithNoUserId(): void
    {
        $data = new KeyValuePair('key', null, false);
        $existence = static::createStub(EntityExistence::class);
        $existence->method('exists')->willReturn(true);
        $parameters = new WriteParameterBag(
            static::createStub(EntityDefinition::class),
            $this->createWriteContext(null),
            '/',
            new WriteCommandQueue(),
        );

        $result = iterator_to_array($this->fieldSerializer->encode(
            new UpdatedByField([Context::USER_SCOPE]),
            $existence,
            $data,
            $parameters
        ));

        static::assertSame(['updated_by_id' => null], $result);
    }

    private function createWriteContext(?string $userId, string $versionId = Defaults::LIVE_VERSION): WriteContext
    {
        $context = Context::createDefaultContext(new AdminApiSource($userId))->createWithVersionId($versionId);

        return WriteContext::createFromContext($context);
    }
}
