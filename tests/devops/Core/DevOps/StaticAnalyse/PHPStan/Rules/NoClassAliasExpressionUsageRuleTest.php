<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\NoClassAliasExpressionUsageRule;
use Contena\Core\Framework\Log\Package;
use Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage;

/**
 * @internal
 *
 * @extends RuleTestCase<NoClassAliasExpressionUsageRule>
 */
#[Package('framework')]
class NoClassAliasExpressionUsageRuleTest extends RuleTestCase
{
    public function testOldClassNameUsagesAreReported(): void
    {
        $message = 'Class alias "Contena\Tests\Legacy\UnregisteredClass" is kept only for backwards compatibility. Use "Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage" instead.';

        $this->analyse([__DIR__ . '/data/NoClassAliasUsageRule/ClassAliasUsage.php'], [
            [$message, 16],
            [$message, 19],
            [$message, 20],
            [$message, 21],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoClassAliasExpressionUsageRule(new ClassAliasMap([
            'Contena\Tests\Legacy\UnregisteredClass' => ClassMovedAttributeUsage::class,
        ]));
    }
}
