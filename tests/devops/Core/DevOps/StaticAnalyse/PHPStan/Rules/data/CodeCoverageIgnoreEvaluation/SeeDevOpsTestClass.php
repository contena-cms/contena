<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\DevOps\Core\Installer\InstallerKernelTest
 */
class SeeDevOpsTestClass
{
    public function boot(): void
    {
        $this->ensureEnvironment();
    }

    private function ensureEnvironment(): void
    {
    }
}
