<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\CustomField\CustomFieldEvents;
use Contena\Core\System\CustomField\CustomFieldException;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CustomFieldService::class)]
class CustomFieldServiceTest extends TestCase
{
    private const string VALID_NAME = 'valid_name';

    private const string INVALID_NAME = 'invalid-name';

    private Connection&Stub $connection;

    private CustomFieldService $customFieldService;

    private WriteContext $writeContext;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->customFieldService = new CustomFieldService($this->connection);
        $this->writeContext = WriteContext::createFromContext(
            Context::createDefaultContext()
        );
    }

    /**
     * @param class-string<object> $expected
     */
    #[DataProvider('getCustomFieldValues')]
    public function testGetCustomField(?string $type, string $expected): void
    {
        $attributeName = 'test';
        $this->connection->method('fetchAllKeyValue')->willReturn([
            $attributeName => $type,
        ]);

        $result = $this->customFieldService->getCustomField($attributeName);

        static::assertInstanceOf($expected, $result);
        static::assertInstanceOf(ApiAware::class, $result->getFlags()[0]);
    }

    /**
     * @return iterable<string, array{type: string|null, expected: class-string<Field>|null}>
     */
    public static function getCustomFieldValues(): iterable
    {
        yield 'int' => ['type' => CustomFieldTypes::INT, 'expected' => IntField::class];
        yield 'float' => ['type' => CustomFieldTypes::FLOAT, 'expected' => FloatField::class];
        yield 'bool' => ['type' => CustomFieldTypes::BOOL, 'expected' => BoolField::class];
        yield 'datetime' => ['type' => CustomFieldTypes::DATETIME, 'expected' => DateTimeField::class];
        yield 'text' => ['type' => CustomFieldTypes::TEXT, 'expected' => LongTextField::class];
        yield 'html' => ['type' => CustomFieldTypes::HTML, 'expected' => LongTextField::class];
        yield 'unknown' => ['type' => 'unknown', 'expected' => JsonField::class];
    }

    #[DataProvider('getCustomFieldNames')]
    public function testValidateCustomFieldInvalidName(string $name, bool $error): void
    {
        $command = $this->createCommand($name);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        if ($error) {
            static::expectExceptionObject(
                CustomFieldException::customFieldNameInvalid($name)
            );
        } else {
            static::expectNotToPerformAssertions();
        }

        $this->customFieldService->validateBeforeWrite($event);
    }

    /**
     * @return iterable<string, array{name: string, error: bool}>
     */
    public static function getCustomFieldNames(): iterable
    {
        yield 'valid' => ['name' => self::VALID_NAME, 'error' => false];
        yield 'valid: start with underscore' => ['name' => '_valid_name', 'error' => false];
        yield 'valid: contains digits' => ['name' => 'valid_name_123', 'error' => false];
        yield 'valid: contains ASCII chars' => ['name' => 'valid_name_äöü', 'error' => false];
        yield 'invalid: start with digits' => ['name' => '123_invalid_name', 'error' => true];
        yield 'invalid: start with special chars' => ['name' => '@_invalid_name', 'error' => true];
        yield 'invalid: contains spaces' => ['name' => 'invalid name', 'error' => true];
        yield 'invalid: contains hyphens' => ['name' => 'invalid-name', 'error' => true];
        yield 'invalid: contains new line' => ['name' => 'invalid\nName', 'error' => true];
    }

    public function testGetCustomFieldNameNotExistingWillFallbackToJson(): void
    {
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $result = $this->customFieldService->getCustomField('test');
        static::assertInstanceOf(JsonField::class, $result);
    }

    public function testGetCustomFieldShouldNotRefetch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                ['test' => CustomFieldTypes::TEXT],
            ]);

        $customFieldService = new CustomFieldService($connection);
        $customFieldService->getCustomField('test');
        $customFieldService->getCustomField('test');
    }

    public function testGetCustomFieldShouldNotRefetchWithoutFields(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);

        $customFieldService = new CustomFieldService($connection);
        $customFieldService->getCustomField('test');
        $customFieldService->getCustomField('test');
    }

    public function testReset(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchAllKeyValue')
            ->willReturnOnConsecutiveCalls(
                [],
                ['test' => CustomFieldTypes::TEXT],
            );

        $customFieldService = new CustomFieldService($connection);
        static::assertInstanceOf(JsonField::class, $customFieldService->getCustomField('test'));
        $customFieldService->reset();
        static::assertInstanceOf(LongTextField::class, $customFieldService->getCustomField('test'));
    }

    public function testSubscribedEvents(): void
    {
        $events = CustomFieldService::getSubscribedEvents();

        static::assertSame(
            [
                CustomFieldEvents::CUSTOM_FIELD_DELETED_EVENT => 'reset',
                CustomFieldEvents::CUSTOM_FIELD_WRITTEN_EVENT => 'reset',
                EntityWriteEvent::class => 'validateBeforeWrite',
            ],
            $events
        );
    }

    public function testValidateWithoutNameShouldSkipValidation(): void
    {
        $command = $this->createCommand(null);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidateWithDifferentEntityShouldSkipValidation(): void
    {
        $command = $this->createCommand(
            self::INVALID_NAME,
            MediaDefinition::ENTITY_NAME,
            [MediaDefinition::class]
        );

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidateWithEmptyCommandsShouldSkipValidation(): void
    {
        $event = EntityWriteEvent::create(
            $this->writeContext,
            [],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    public function testValidNameShouldPassValidation(): void
    {
        $command = $this->createCommand(self::VALID_NAME);

        $event = EntityWriteEvent::create(
            $this->writeContext,
            [$command],
        );

        static::expectNotToPerformAssertions();

        $this->customFieldService->validateBeforeWrite($event);
    }

    /**
     * @param array<int, class-string<EntityDefinition>> $registryDefinitions
     */
    private function createCommand(
        ?string $name,
        string $commandEntity = CustomFieldSetDefinition::ENTITY_NAME,
        array $registryDefinitions = [CustomFieldSetDefinition::class]
    ): InsertCommand {
        $registry = new StaticDefinitionInstanceRegistry(
            $registryDefinitions,
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $payload = $name ? ['name' => $name] : [];

        $entityExistence = new EntityExistence(
            $commandEntity,
            $payload,
            true,
            false,
            false,
            []
        );

        return new InsertCommand(
            $registry->getByEntityName($commandEntity),
            $payload,
            ['id' => Uuid::randomBytes()],
            $entityExistence,
            '/0'
        );
    }
}
