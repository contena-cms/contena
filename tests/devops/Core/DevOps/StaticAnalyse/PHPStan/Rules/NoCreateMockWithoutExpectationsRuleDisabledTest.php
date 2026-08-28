<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NoCreateMockWithoutExpectationsRule>
 */
class NoCreateMockWithoutExpectationsRuleDisabledTest extends RuleTestCase
{
    public function testEmptyEnabledNamespacesDisableTheRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/Cases.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule(
            new Configuration(['createMockWithoutExpectationsEnabledNamespaces' => []]),
        );
    }
}
