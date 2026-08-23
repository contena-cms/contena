<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Contena\Tests\Integration\Definitely\Not\A\RealTest;

/**
 * @codeCoverageIgnore
 *
 * @see RealTest
 */
class SeeMissingIntegrationTestClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
