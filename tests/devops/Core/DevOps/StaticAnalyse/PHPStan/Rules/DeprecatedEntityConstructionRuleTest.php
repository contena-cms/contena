<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\DeprecatedEntityConstructionRule;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<DeprecatedEntityConstructionRule>
 */
#[Package('framework')]
class DeprecatedEntityConstructionRuleTest extends RuleTestCase
{
    public function testRequiresConstructionGuardsForDefinitionsAndDataObjects(): void
    {
        $this->analyse([__DIR__ . '/data/DeprecatedMethodsThrowDeprecationRule/DeprecatedEntities.php'], [
            ['Deprecated DAL class "Contena\\Core\\DevOps\\MyFakeNamespace\\UnguardedDefinition" must declare a constructor starting with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 39],
            ['Deprecated DAL class "Contena\\Core\\DevOps\\MyFakeNamespace\\WrongFlagCollection" must declare a constructor starting with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 81],
            ['Deprecated DAL class "Contena\\Core\\DevOps\\MyFakeNamespace\\RemovedEntity" must declare a constructor starting with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 100],
            ['Deprecated DAL class "Contena\\Core\\DevOps\\MyFakeNamespace\\UnguardedFutureCollection" must declare a constructor starting with "Feature::throwIfActive" for feature flag "v6.9.0.0".', 113],
            ['Deprecated DAL class "Contena\\Core\\DevOps\\MyFakeNamespace\\LateGuardCollection" must declare a constructor starting with "Feature::throwIfActive" for feature flag "v6.8.0.0".', 122],
        ]);
    }

    protected function getRule(): Rule
    {
        return new DeprecatedEntityConstructionRule();
    }
}
