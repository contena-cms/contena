<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal\InternalClassRule;
use Contena\Core\Framework\Log\Package;

require_once __DIR__ . '/data/InternalClassRule/DirectCompilerPasses.php';
require_once __DIR__ . '/data/InternalClassRule/InheritedCompilerPasses.php';
require_once __DIR__ . '/data/InternalClassRule/BecomesInternalCompilerPasses.php';

/**
 * @internal
 *
 * @extends RuleTestCase<InternalClassRule>
 */
class InternalClassRuleTest extends RuleTestCase
{
    private const ERROR = 'Compiler passes must be flagged @internal to not be captured by the BC checker.';

    public function testDirectCompilerPassesRequireInternal(): void
    {
        $this->analyse([__DIR__ . '/data/InternalClassRule/DirectCompilerPasses.php'], [
            [self::ERROR, 8],
            [self::ERROR, 15],
            [self::ERROR, 25],
        ]);
    }

    public function testInheritedCompilerPassesRequireTheirOwnInternalAnnotation(): void
    {
        $this->analyse([__DIR__ . '/data/InternalClassRule/InheritedCompilerPasses.php'], [
            [self::ERROR, 18],
            [self::ERROR, 22],
            [self::ERROR, 26],
        ]);
    }

    public function testCompilerPassesWithBecomesInternalAreAccepted(): void
    {
        $this->analyse([__DIR__ . '/data/InternalClassRule/BecomesInternalCompilerPasses.php'], [
            [self::ERROR, 25],
            [self::ERROR, 29],
        ]);
    }

    protected function getRule(): Rule
    {
        return new InternalClassRule();
    }
}
