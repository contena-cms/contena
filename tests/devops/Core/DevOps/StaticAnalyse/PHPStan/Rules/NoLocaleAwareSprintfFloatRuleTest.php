<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoLocaleAwareSprintfFloatRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @internal
 *
 * @extends RuleTestCase<NoLocaleAwareSprintfFloatRule>
 */
class NoLocaleAwareSprintfFloatRuleTest extends RuleTestCase
{
    private const string ERROR = 'Do not use the locale-aware %f specifier in sprintf(). Use %F for locale-independent float serialization.';

    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NoLocaleAwareSprintfFloatRule/SprintfUsage.php'], [
            [self::ERROR, 10],
            [self::ERROR, 13],
            [self::ERROR, 14],
            [self::ERROR, 15],
            [self::ERROR, 16],
            [self::ERROR, 17],
            [self::ERROR, 20],
            [self::ERROR, 21],
            [self::ERROR, 25],
        ]);
    }

    /**
     * @return NoLocaleAwareSprintfFloatRule
     */
    protected function getRule(): Rule
    {
        return new NoLocaleAwareSprintfFloatRule($this->createReflectionProvider());
    }
}
