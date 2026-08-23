<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestReflectionClassInterface;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;

/**
 * @internal
 */
class TestRuleHelperTest extends TestCase
{
    #[DataProvider('classProvider')]
    public function testIsTestClass(string $className, bool $extendsTestCase, bool $isTestClass, bool $isUnitTestClass): void
    {
        $classReflection = $this->createMock(TestReflectionClassInterface::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        if ($extendsTestCase) {
            $parentClass = $this->createMock(TestReflectionClassInterface::class);
            $parentClass
                ->method('getName')
                ->willReturn(TestCase::class);

            $classReflection
                ->method('getParents')
                ->willReturn([$parentClass]);
        }

        static::assertSame($isTestClass, TestRuleHelper::isTestClass($classReflection));
        static::assertSame($isUnitTestClass, TestRuleHelper::isUnitTestClass($classReflection));
    }

    public function testIsUnitTestClassUsesConfiguredNamespaces(): void
    {
        $classReflection = $this->createTestClassReflection('Contena\Commercial\Tests\Unit\SomeTestClass');

        static::assertFalse(TestRuleHelper::isUnitTestClass($classReflection));
        static::assertTrue(TestRuleHelper::isUnitTestClass($classReflection, ['Contena\\Commercial\\Tests\\Unit\\']));
    }

    public static function classProvider(): \Generator
    {
        yield [
            'className' => 'Contena\Some\NonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Contena\Commercial\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Contena\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Contena\Tests\Unit\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => true,
        ];

        yield [
            'className' => 'Contena\Tests\Integration\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Contena\Tests\SomeNonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];
    }

    private function createTestClassReflection(string $className): TestReflectionClassInterface
    {
        $classReflection = $this->createMock(TestReflectionClassInterface::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        $parentClass = $this->createMock(TestReflectionClassInterface::class);
        $parentClass
            ->method('getName')
            ->willReturn(TestCase::class);

        $classReflection
            ->method('getParents')
            ->willReturn([$parentClass]);

        return $classReflection;
    }
}
