<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(FloatFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class FloatFieldSerializerTest extends TestCase
{
    private FloatFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new FloatFieldSerializer(
            $validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = new FloatField('test', 'test')->addFlags(new Choice([1.5]));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1.7, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.7], $encoded);
    }

    public function testChoiceStrictAcceptsNumericStringMatchingChoice(): void
    {
        $field = new FloatField('test', 'test')->addFlags(new Choice([1.5, 2.5], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', '1.5', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.5], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = new FloatField('test', 'test')->addFlags(new Choice([1.5, 2.5], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1.7, true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 1.7),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new DateDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
