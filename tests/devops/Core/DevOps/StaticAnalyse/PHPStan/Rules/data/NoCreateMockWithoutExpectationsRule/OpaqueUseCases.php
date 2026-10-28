<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoCreateMockWithoutExpectationsRule;

use PHPUnit\Framework\TestCase;

interface OpaqueDependency
{
    public function value(): string;
}

interface OpaqueLocator
{
}

/**
 * @internal
 *
 * The helper embeds the property in another double's willReturnMap() — data consumption, not a hidden
 * expectation. FLAGGED as mixed, naming testBare().
 */
class WillReturnMapEmbeddedPropertyCases extends TestCase
{


    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
        $this->locator = $this->createMock(OpaqueLocator::class);
    }

    public function testCovered(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->createSut()->lookup());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->lookup());
    }

}

/**
 * @internal
 *
 * The property is created inside a fixture helper instead of setUp(): the tests reaching that helper own
 * an instance. FLAGGED as mixed, naming testBare() — testNotOwning never creates the double and stays out.
 */
class HelperCreatedPropertyCases extends TestCase
{

    public function testCovered(): void
    {
        $sut = $this->createSut();
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $sut->run());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->run());
    }

    public function testNotOwning(): void
    {
        static::assertTrue(true);
    }

}

/**
 * @internal
 *
 * The property reaches the SUT constructor wrapped in an array literal. FLAGGED as mixed, naming testBare().
 */
class ArrayWrappedPropertyCases extends TestCase
{

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testCovered(): void
    {
        $this->dependency->expects($this->once())->method('value')->willReturn('a');
        static::assertSame('a', $this->createSut()->first());
    }

    public function testBare(): void
    {
        static::assertSame('', $this->createSut()->first());
    }

}

/**
 * @internal
 *
 * The fixture helper re-binds its parameter with `$dep = $dep ?? <fresh stub>` before forwarding it into
 * the SUT — the double still only flows into production code. FLAGGED as stub.
 */
class SelfDefaultingParamCases extends TestCase
{
    public function testOne(): void
    {
        $dependency = $this->createMock(OpaqueDependency::class);
        $dependency->method('value')->willReturn('a');

        static::assertSame('a', $this->createSut($dependency)->run());
    }

}

/**
 * @internal
 *
 * A helper aliases the property into a local that stays clean (constructor forwarding only). FLAGGED as stub.
 */
class AliasFollowedPropertyCases extends TestCase
{

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testOne(): void
    {
        static::assertSame('', $this->createSut()->run());
    }

}

/**
 * @internal
 *
 * The alias itself gets ->expects()-ed — the property must keep its conservative skip. NOT flagged.
 */
class AliasExpectedPropertyCases extends TestCase
{

    protected function setUp(): void
    {
        $this->dependency = $this->createMock(OpaqueDependency::class);
    }

    public function testOne(): void
    {
        $this->configureAlias();
        static::assertSame('a', $this->dependency->value());
    }

    public function testTwo(): void
    {
        static::assertSame('', $this->dependency->value());
    }

    private function configureAlias(): void
    {
        $dependency = $this->dependency;
        $dependency->expects($this->once())->method('value')->willReturn('a');
    }
}

/**
 * @internal
 */
class OpaqueSut
{

    public function run(): string
    {
        return $this->dependency->value();
    }
}

/**
 * @internal
 */
class OpaqueLocatorSut
{

    public function lookup(): string
    {
        return $this->locator->get('key')->value();
    }
}

/**
 * @internal
 */
class OpaqueCollectionSut
{
    /**
     * @param array<string, OpaqueDependency> $dependencies
     */
    public function __construct(private readonly array $dependencies)
    {
    }

    public function first(): string
    {
        foreach ($this->dependencies as $dependency) {
            return $dependency->value();
        }

        return '';
    }
}
