<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\DataProviderRowArityRule;

/**
 * @internal
 *
 * @extends RuleTestCase<DataProviderRowArityRule>
 */
class DataProviderRowArityRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/DataProviderRowArity/data-provider-row-arity-cases.php'],
            [
                [
                    'Data provider Contena\Tests\Unit\Core\DataProviderRowArityFixture\DataProviderRowArityCases::attributeOvershootProvider yields a row with 4 entries, but the consuming test Contena\Tests\Unit\Core\DataProviderRowArityFixture\DataProviderRowArityCases::testAttributeOvershoot only accepts 2 parameter(s). PHPUnit 12 errors hard on this.',
                    19,
                ],
                [
                    'Data provider Contena\Tests\Unit\Core\DataProviderRowArityFixture\DataProviderRowArityCases::returnArrayProvider yields a row with 2 entries, but the consuming test Contena\Tests\Unit\Core\DataProviderRowArityFixture\DataProviderRowArityCases::testReturnArrayOvershoot only accepts 1 parameter(s). PHPUnit 12 errors hard on this.',
                    61,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new DataProviderRowArityRule();
    }
}
