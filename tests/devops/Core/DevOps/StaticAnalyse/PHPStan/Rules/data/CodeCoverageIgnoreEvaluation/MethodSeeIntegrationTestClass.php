<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Contena\Tests\Integration\Core\Framework\Plugin\KernelPluginIntegrationTest;

class MethodSeeIntegrationTestClass
{
    /**
     * @codeCoverageIgnore
     *
     * @see KernelPluginIntegrationTest
     */
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
