<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\PrimaryKeyBag;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(WriteCommandExtractor::class)]
class WriteCommandExtractorTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('writeProtectedFieldsProvider')]
    public function testExceptionForWriteProtectedFields(array $payload, ContextSource $scope, bool $valid): void
    {
        $definition = new class extends EntityDefinition {
            final public const string ENTITY_NAME = 'webhook';

            public function getEntityName(): string
            {
                return self::ENTITY_NAME;
            }

            public function getDefaults(): array
            {
                return [
                    'errorCount' => 0,
                ];
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
                    new StringField('name', 'name')->addFlags(new Required()),
                    new IntField('error_count', 'errorCount', 0)->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
                ]);
            }
        };

        $data = [
            'name' => 'My super webhook',
        ];
        $data = \array_replace($data, $payload);

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $extractor = new WriteCommandExtractor(
            static::createStub(EntityWriteGateway::class),
            $registry
        );
        $context = Context::createDefaultContext($scope);

        $parameters = new WriteParameterBag(
            $registry->get($definition::class),
            WriteContext::createFromContext($context),
            '',
            new WriteCommandQueue(),
            new PrimaryKeyBag()
        );

        $extractor->extract($data, $parameters);

        if ($valid) {
            static::assertCount(0, $parameters->getContext()->getExceptions()->getExceptions());

            return;
        }

        static::assertCount(1, $parameters->getContext()->getExceptions()->getExceptions());
        $exception = $parameters->getContext()->getExceptions()->getExceptions();
        $exception = \array_shift($exception);

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);

        $violations = $exception->getViolations();
        static::assertCount(1, $violations);
        static::assertInstanceOf(ConstraintViolation::class, $violations->get(0));
        static::assertStringContainsString('This field is write-protected. (Got: "user" scope and "system" is required)', (string) $violations->get(0)->getMessage());
    }

    public static function writeProtectedFieldsProvider(): \Generator
    {
        yield 'Test write webhook with system source and valid error count' => [
            ['errorCount' => 10],
            new SystemSource(),
            true,
        ];

        yield 'Test write webhook with user source and valid error count' => [
            ['errorCount' => 10],
            new AdminApiSource(Uuid::randomHex()),
            false,
        ];

        yield 'Test write without error count and user source' => [
            [],
            new AdminApiSource(Uuid::randomHex()),
            true,
        ];
    }

    public function testCreateUpdateCommandWithImmutableChanges(): void
    {
        $definition = new class extends EntityDefinition {
            final public const string ENTITY_NAME = 'immutable_test';

            public function getEntityName(): string
            {
                return self::ENTITY_NAME;
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
                    new StringField('name', 'name')->addFlags(new Required()),
                    new StringField('immutable_field', 'immutableField')->addFlags(new Immutable()),
                ]);
            }
        };

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $existenceGateway = $this->createMock(EntityWriteGatewayInterface::class);
        $existenceGateway->expects($this->exactly(2))
            ->method('getExistence')
            ->willReturnOnConsecutiveCalls(
                new EntityExistence($definition::ENTITY_NAME, [], false, false, false, []),
                new EntityExistence($definition::ENTITY_NAME, [], true, false, false, [])
            );

        $extractor = new WriteCommandExtractor(
            $existenceGateway,
            $registry
        );

        $id = Uuid::randomHex();

        $createParameters = new WriteParameterBag(
            $registry->get($definition::class),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue(),
            new PrimaryKeyBag()
        );

        $extractor->extract([
            'id' => $id,
            'name' => 'immutable entity',
            'immutableField' => 'initial',
        ], $createParameters);

        static::assertCount(1, $createParameters->getCommandQueue()->getCommands());
        static::assertArrayHasKey('immutable_test', $createParameters->getCommandQueue()->getCommands());

        $commands = $createParameters->getCommandQueue()->getCommands()['immutable_test'];

        static::assertCount(1, $commands);
        $command = $commands[0];
        static::assertInstanceOf(InsertCommand::class, $command);
        static::assertCount(0, $createParameters->getContext()->getExceptions()->getExceptions());

        $updateParameters = new WriteParameterBag(
            $registry->get($definition::class),
            WriteContext::createFromContext(Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()))),
            '',
            new WriteCommandQueue(),
            new PrimaryKeyBag()
        );

        $extractor->extract([
            'id' => $id,
            'name' => 'immutable entity',
            'immutableField' => 'updated',
        ], $updateParameters);

        static::assertCount(1, $updateParameters->getCommandQueue()->getCommands());
        static::assertArrayHasKey('immutable_test', $updateParameters->getCommandQueue()->getCommands());

        $commands = $updateParameters->getCommandQueue()->getCommands()['immutable_test'];

        static::assertCount(1, $commands);
        $command = $commands[0];

        static::assertInstanceOf(UpdateCommand::class, $command);
        static::assertCount(0, $createParameters->getContext()->getExceptions()->getExceptions());

        static::assertTrue($command->requiresChangeSet());
        static::assertSame(['immutable_field'], $command->getImmutableFieldsChanges());
    }
}
