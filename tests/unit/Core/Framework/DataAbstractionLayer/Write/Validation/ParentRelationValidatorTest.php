<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ParentRelationValidator::class)]
class ParentRelationValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private ParentRelationValidator $validator;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [CustomFieldTestDefinition::class, DateDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->validator = new ParentRelationValidator($this->registry);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ParentRelationValidator::getSubscribedEvents();
        static::assertCount(1, $events);
        static::assertSame('preValidate', $events[PreWriteValidationEvent::class]);
    }

    public function testPreValidateIgnoresNotParentAware(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('_date_field_test'), ['id' => $id, 'parent_id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
                new UpdateCommand($this->registry->getByEntityName('_date_field_test'), ['id' => $id, 'parent_id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/update'),
            ]
        );

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testPreValidateCatchesInsert(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('attribute_test'), ['id' => $id, 'parent_id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());
        $violation = $exception->getViolations()->get(0);
        static::assertSame(ParentRelationValidator::VIOLATION_PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES, $violation->getCode());
        static::assertSame(
            \sprintf('The attribute_test entity with id "%s" can not reference to itself as parent.', Uuid::fromBytesToHex($id)),
            $violation->getMessage()
        );
        static::assertSame('/insert/parentId', $violation->getPropertyPath());
    }

    public function testPreValidateCatchesUpdate(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand($this->registry->getByEntityName('attribute_test'), ['id' => $id, 'parent_id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/update'),
            ]
        );

        $this->validator->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());
        $violation = $exception->getViolations()->get(0);
        static::assertSame(ParentRelationValidator::VIOLATION_PARENT_RELATION_DOES_NOT_ALLOW_SELF_REFERENCES, $violation->getCode());
        static::assertSame(
            \sprintf('The attribute_test entity with id "%s" can not reference to itself as parent.', Uuid::fromBytesToHex($id)),
            $violation->getMessage()
        );
        static::assertSame('/update/parentId', $violation->getPropertyPath());
    }

    public function testPreValidateAllowsNonSelfReferences(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand($this->registry->getByEntityName('attribute_test'), ['id' => $id, 'parent_id' => Uuid::randomBytes()], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
