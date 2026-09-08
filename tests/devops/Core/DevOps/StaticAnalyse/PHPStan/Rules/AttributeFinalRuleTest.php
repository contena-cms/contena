<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\AttributeFinalRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @internal
 *
 * @extends  RuleTestCase<AttributeFinalRule>
 */
class AttributeFinalRuleTest extends RuleTestCase
{
    public function testFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/FinalAttributeClass.php',
        ], []);
    }

    public function testNonFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/NonFinalAttributeClass.php',
        ], [[
            'Attribute classes must be declared final.',
            5,
        ]]);
    }

    protected function getRule(): Rule
    {
        return new AttributeFinalRule();
    }
}
