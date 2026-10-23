<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoReflectionOnNonPublicMethodsRule;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoReflectionOnNonPublicMethodsRule>
 */
class NoReflectionOnNonPublicMethodsRuleTest extends RuleTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/data/NoReflectionOnNonPublicMethodsRule/';

    private const TARGET = 'Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture\ReflectionTarget';

    #[TestDox('Flags reflective access to non-public Contena methods in tests, allows public, vendor, and test-support targets')]
    public function testRule(): void
    {
        $this->analyse([self::FIXTURE_DIR . 'Cases.php'], [
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'hiddenCalculation'), 17],
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'guardedStep'), 25],
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'hiddenCalculation'), 33],
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'hiddenCalculation'), 43],
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'hiddenCalculation'), 51],
            [\sprintf(NoReflectionOnNonPublicMethodsRule::ERROR_NON_PUBLIC, self::TARGET, 'guardedStep'), 61],
            [NoReflectionOnNonPublicMethodsRule::ERROR_SET_ACCESSIBLE, 71],
        ]);
    }

    #[TestDox('Does not apply outside test classes')]
    public function testNonTestClassPasses(): void
    {
        $this->analyse([self::FIXTURE_DIR . 'NonTestClass.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoReflectionOnNonPublicMethodsRule(self::createReflectionProvider());
    }
}
