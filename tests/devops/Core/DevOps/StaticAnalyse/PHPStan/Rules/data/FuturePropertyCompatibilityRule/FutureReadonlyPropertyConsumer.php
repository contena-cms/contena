<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyCompatibilityRule;

class FutureReadonlyPropertyConsumer extends FutureReadonlyProperty
{
    public function assignValue(): void
    {
        $this->value = 'value';
    }
}
