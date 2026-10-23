<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\Fixture;


/**
 * Fixture for the NoReflectionOnNonPublicMethodsRule test: a Contena class with every method
 * visibility. It lives in src so the rule's ReflectionProvider lookup resolves it through the
 * regular autoloader, which no class in a tests namespace can satisfy (the rule skips those).
 *
 * @internal
 */
class ReflectionTarget
{
    public function publicApi(): void
    {
    }

    public function touchHidden(): void
    {
        $this->hiddenCalculation();
    }

    protected function guardedStep(): void
    {
    }

    private function hiddenCalculation(): void
    {
        $this->guardedStep();
    }
}
