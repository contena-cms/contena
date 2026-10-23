<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyCompatibilityRule;

use Contena\Core\Framework\Deprecation\BCChange\BecomesReadonly;

class FutureReadonlyProperty
{
    #[BecomesReadonly(version: 'v6.8.0')]
    protected string $value = 'value';

    public function ownAssignment(): void
    {
        $this->value = 'value';
    }
}
