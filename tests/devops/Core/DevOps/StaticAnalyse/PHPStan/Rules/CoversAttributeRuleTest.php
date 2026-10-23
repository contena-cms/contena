<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\CoversAttributeRule;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<CoversAttributeRule>
 */
class CoversAttributeRuleTest extends RuleTestCase
{
    public function testAllowsConfiguredUnitTestNamespace(): void
    {
        $this->analyse([__DIR__ . '/data/CoversAttributeRule/Unit/ConfiguredUnitFixture.php'], []);
    }

    public function testAllowsCoversTraitAttribute(): void
    {
        $this->analyse([__DIR__ . '/data/CoversAttributeRule/Unit/CoversTraitFixture.php'], []);
    }

    public function testRejectsCoversAttributeOutsideConfiguredUnitTestNamespace(): void
    {
        $this->analyse([__DIR__ . '/data/CoversAttributeRule/Integration/IntegrationFixture.php'], [
            [
                'Only Unit & Migration test classes can have CoversClass, CoversTrait, CoversFunction or CoversNothing attribute',
                8,
            ],
        ]);
    }

    /**
     * @return CoversAttributeRule
     */
    protected function getRule(): Rule
    {
        return new CoversAttributeRule(new Configuration([
            'allowedUnitTestClassNamespaces' => [
                'Contena\\Tests\\DevOps\\Core\\DevOps\\StaticAnalyse\\PHPStan\\Rules\\data\\CoversAttributeRule\\Unit\\',
            ],
        ]));
    }
}
