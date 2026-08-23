<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\AssertEqualsCanonicalizingListArgumentRule;

/**
 * @internal
 *
 * @extends RuleTestCase<AssertEqualsCanonicalizingListArgumentRule>
 */
class AssertEqualsCanonicalizingListArgumentRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/AssertEqualsCanonicalizingListArgumentRule/Cases.php'], [
            // line 32: arg #2 is a bare variable
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 2), 32],
            // line 34: arg #1 is a bare variable
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 1), 34],
            // line 36: arg #2 is a keyed array literal
            [\sprintf(AssertEqualsCanonicalizingListArgumentRule::ERROR_MESSAGE, 2), 36],
        ]);
    }

    protected function getRule(): Rule
    {
        return new AssertEqualsCanonicalizingListArgumentRule();
    }
}
