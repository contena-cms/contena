<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
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
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\Range;
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
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5]));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1.7, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.7], $encoded);
    }

    public function testChoiceStrictAcceptsNumericStringMatchingChoice(): void
    {
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5, 2.5], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', '1.5', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1.5], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new FloatField('test', 'test'))->addFlags(new Choice([1.5, 2.5], strict: true));
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

    #[DataProvider('provideValuesInRange')]
    public function testRangeAcceptsValuesWithinMinAndMax(?float $min, ?float $max, float $value): void
    {
        $field = new FloatField('test', 'test', $min, $max);
        $field->compile($this->definitionInstanceRegistry);

        $encoded = iterator_to_array($this->serializer->encode($field, EntityExistence::createEmpty(), new KeyValuePair('test', $value, true), $this->createWriteParameterBag()));

        static::assertSame(['test' => $value], $encoded);
    }

    /**
     * @return iterable<string, array{float|null, float|null, float}>
     */
    public static function provideValuesInRange(): iterable
    {
        yield 'min boundary' => [0.0, 5.0, 0.0];
        yield 'max boundary' => [0.0, 5.0, 5.0];
        yield 'between min and max' => [0.0, 5.0, 2.5];
        yield 'only min' => [1.0, null, 1000.0];
        yield 'only max' => [null, 5.0, -1000.0];
    }

    #[DataProvider('provideValuesOutOfRange')]
    public function testRangeRejectsValuesOutsideMinAndMax(?float $min, ?float $max, float $value, string $expectedCode): void
    {
        $field = new FloatField('test', 'test', $min, $max);
        $field->compile($this->definitionInstanceRegistry);

        try {
            iterator_to_array($this->serializer->encode($field, EntityExistence::createEmpty(), new KeyValuePair('test', $value, true), $this->createWriteParameterBag()));
            static::fail('A value outside the range has to be rejected');
        } catch (WriteConstraintViolationException $exception) {
            $violations = $exception->getViolations();

            static::assertCount(1, $violations);
            static::assertSame($expectedCode, $violations->get(0)->getCode());
            static::assertSame('/test', $violations->get(0)->getPropertyPath());
        }
    }

    /**
     * @return iterable<string, array{float|null, float|null, float, string}>
     */
    public static function provideValuesOutOfRange(): iterable
    {
        yield 'below min' => [0.0, 5.0, -0.1, Range::NOT_IN_RANGE_ERROR];
        yield 'above max' => [0.0, 5.0, 5.1, Range::NOT_IN_RANGE_ERROR];
        yield 'below only min' => [1.0, null, 0.5, Range::TOO_LOW_ERROR];
        yield 'above only max' => [null, 5.0, 5.5, Range::TOO_HIGH_ERROR];
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new BlogDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
