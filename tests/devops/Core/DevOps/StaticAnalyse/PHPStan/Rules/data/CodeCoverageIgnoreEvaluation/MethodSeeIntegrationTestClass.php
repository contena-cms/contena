<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;


class MethodSeeIntegrationTestClass
{
    /**
     * @codeCoverageIgnore
     *
     * @see WebhookHealthServiceTest
     */
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
