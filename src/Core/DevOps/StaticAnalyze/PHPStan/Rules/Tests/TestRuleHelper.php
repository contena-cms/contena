<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class TestRuleHelper
{
    /**
     * @var list<string>
     */
    private const array UNIT_TEST_CLASS_NAMESPACES = [
        'Contena\\Tests\\Unit\\',
        'Contena\\Tests\\Migration\\',
    ];

    public static function isTestClass(TestReflectionClassInterface|ClassReflection $class): bool
    {
        foreach ($class->getParents() as $parent) {
            if ($parent->getName() === TestCase::class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string>|null $unitTestClassNamespaces
     */
    public static function isUnitTestClass(TestReflectionClassInterface|ClassReflection $class, ?array $unitTestClassNamespaces = null): bool
    {
        if (!static::isTestClass($class)) {
            return false;
        }

        foreach ($unitTestClassNamespaces ?? self::UNIT_TEST_CLASS_NAMESPACES as $unitTestClassNamespace) {
            if (\str_starts_with($class->getName(), $unitTestClassNamespace)) {
                return true;
            }
        }

        return false;
    }
}
