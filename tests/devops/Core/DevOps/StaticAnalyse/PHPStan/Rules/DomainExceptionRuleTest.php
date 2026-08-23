<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\DomainExceptionRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<DomainExceptionRule>
 */
class DomainExceptionRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/DomainExceptionRule/DomainExceptionViolations.php'], [
            [
                'Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena/platform/blob/v6.4.20.0/adr/2022-02-24-domain-exceptions.md',
                9,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/DomainExceptionRule/ExcludedNamespace.php'], [
        ]);
    }

    /**
     * @return DomainExceptionRule
     */
    protected function getRule(): Rule
    {
        return new DomainExceptionRule(
            $this->createReflectionProvider(),
            new Configuration([
                'allowedNonDomainExceptions' => [
                ],
            ])
        );
    }
}
