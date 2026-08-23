<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class IfBranchClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
