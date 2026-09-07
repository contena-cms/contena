<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Codec;

use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformance;
use Contena\Core\Framework\ContentSystem\Layout\Codec\PropertyTypeConformanceValidator;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(PropertyTypeConformanceValidator::class)]
class PropertyTypeConformanceValidatorTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('acceptsConformingElementProvider')]
    #[TestDox('reports no violation for $_dataName')]
    public function testAcceptsAConformingElement(array $properties): void
    {
        static::assertCount(0, $this->validate($this->element($properties)));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function acceptsConformingElementProvider(): iterable
    {
        yield 'a string under a string declaration' => [['headline' => 'Hi']];
        yield 'an integer under an integer declaration' => [['count' => 5]];
        yield 'an integer under a number declaration' => [['ratio' => 5]];
        yield 'a float under a number declaration' => [['ratio' => 1.5]];
        yield 'a boolean under a boolean declaration' => [['featured' => true]];
        yield 'a null under a string declaration' => [['headline' => null]];
        yield 'a null under an integer declaration' => [['count' => null]];
        yield 'a null under a number declaration' => [['ratio' => null]];
        yield 'a null under a boolean declaration' => [['featured' => null]];
        yield 'a value matching the first member of an all-primitive union' => [['spread' => 'wide']];
        yield 'a value matching the second member of an all-primitive union' => [['spread' => 3]];
        yield 'an array under a bare object declaration' => [['config' => ['nested' => 'value']]];
        yield 'a scalar under a bare object declaration' => [['config' => 'whatever']];
        yield 'a value matching no member of a union carrying object' => [['columns' => 'not-an-integer']];
        yield 'a scalar under a declared reference property' => [['blog' => 'oops']];
        yield 'a value under a key the type does not declare' => [['mediaId' => ['not', 'a', 'string']]];
        yield 'an element carrying no properties at all' => [[]];
    }

    #[TestDox('reports one violation per disagreeing key rather than one for the element')]
    public function testReportsOneViolationPerDisagreeingKey(): void
    {
        $violations = $this->validate($this->element(['headline' => 42, 'count' => 'five', 'featured' => true]));

        static::assertCount(2, $violations);
        static::assertSame('[properties][headline]', $violations->get(0)->getPropertyPath());
        static::assertSame('[properties][count]', $violations->get(1)->getPropertyPath());
    }

    #[TestDox('reports nothing, and never reaches the throwing lookup, for a component the registry does not know')]
    public function testEmitsNothingForAnUnregisteredComponent(): void
    {
        // get() throws the way both concrete registries do, so a regression from has()-guarded to unguarded
        // surfaces as the 404 escaping the constraint pass rather than as a missing violation.
        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturn(false);
        $registry->method('get')->willThrowException(ContentSystemException::elementTypeNotFound('CT:Ghost'));

        $element = ['id' => 'el-1', 'component' => 'CT:Ghost', 'properties' => ['headline' => 42]];

        static::assertCount(0, $this->validate($element, $registry));
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    #[DataProvider('rejectsNonConformingElementProvider')]
    #[TestDox('reports one violation naming the declared and actual type for $_dataName')]
    public function testRejectsANonConformingElement(array $properties, string $key, string $declaredType, string $actualType): void
    {
        $violations = $this->validate($this->element($properties));

        static::assertCount(1, $violations);
        static::assertSame('[properties][' . $key . ']', $violations->get(0)->getPropertyPath());
        static::assertSame(
            \sprintf('Property "%s" is declared as "%s" but carries a value of type "%s".', $key, $declaredType, $actualType),
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, string, string}>
     */
    public static function rejectsNonConformingElementProvider(): iterable
    {
        yield 'a non-string value under a string declaration' => [['headline' => ['a', 'b']], 'headline', 'string', 'array'];
        yield 'a non-integer value under an integer declaration' => [['count' => 'five'], 'count', 'integer', 'string'];
        yield 'a string under a number declaration' => [['ratio' => '1.5'], 'ratio', 'number', 'string'];
        yield 'an integer under a boolean declaration' => [['featured' => 1], 'featured', 'boolean', 'int'];
        yield 'a value matching no member of an all-primitive union' => [['spread' => true], 'spread', 'string|integer', 'bool'];
    }

    /**
     * @param array<array-key, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function element(array $properties): array
    {
        return ['id' => 'el-1', 'component' => 'CT:Block', 'properties' => $properties];
    }

    /**
     * @param array<array-key, mixed> $element
     */
    private function validate(array $element, ?AbstractContentSystemElementTypeRegistry $registry = null): ConstraintViolationListInterface
    {
        $validator = new PropertyTypeConformanceValidator($registry ?? $this->registry());

        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                PropertyTypeConformanceValidator::class => $validator,
            ]))
            ->getValidator()
            ->validate($element, new PropertyTypeConformance());
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $specs = ['CT:Block' => new ContentSystemElementTypeSpecification(
            'CT:Block',
            'Block',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'headline' => $this->property('string'),
                'count' => $this->property('integer'),
                'ratio' => $this->property('number'),
                'featured' => $this->property('boolean'),
                'spread' => $this->property(['string', 'integer']),
                'columns' => $this->property(['integer', 'object']),
                'config' => $this->property('object'),
                'blog' => $this->property('Contena\\Core\\Content\\Media\\MediaEntity'),
            ],
            [],
        )];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    /**
     * @param string|list<string> $type
     */
    private function property(string|array $type): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, null), false, '', '', null);
    }
}
