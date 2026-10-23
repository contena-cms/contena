<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class CoalesceClass
{
    public function pick(?string $value): string
    {
        return $value ?? 'default';
    }
}
