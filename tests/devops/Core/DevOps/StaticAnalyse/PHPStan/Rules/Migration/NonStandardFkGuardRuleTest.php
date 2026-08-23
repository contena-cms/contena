<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Migration;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration\NonStandardFkGuardRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NonStandardFkGuardRule>
 */
class NonStandardFkGuardRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $message = 'Raw ALTER TABLE or index DDL in a migration must go through '
            . 'MigrationStep::executeDdlStatement() to survive MySQL 8.4 non-standard '
            . 'foreign-key drift (MySQL bug #118151).';

        $this->analyse([
            __DIR__ . '/../data/NonStandardFkGuardRule/Migration1785716585UnguardedDdl.php',
            __DIR__ . '/../data/NonStandardFkGuardRule/Migration1785716586GuardedDdl.php',
        ], [
            [$message, 20],
            [$message, 21],
            [$message, 22],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NonStandardFkGuardRule();
    }
}
