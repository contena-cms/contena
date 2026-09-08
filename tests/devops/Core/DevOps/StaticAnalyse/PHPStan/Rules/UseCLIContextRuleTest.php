<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\UseCLIContextRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * @internal
 *
 * @extends RuleTestCase<UseCLIContextRule>
 */
class UseCLIContextRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testUseCLIContextRuleTest(): void
    {
        $this->analyse([__DIR__ . '/data/UseCLIContextRule/TestCommand.php'], [
            [
                'Method Context::createDefaultContext() should not be used in CLI context. Use Context::createCLIContext() instead.',
                17,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/UseCLIContextRule/TaskHandler.php'], [
            [
                'Method Context::createDefaultContext() should not be used in CLI context. Use Context::createCLIContext() instead.',
                15,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/UseCLIContextRule/NonRestrictedClass.php'], []);
    }

    /**
     * @return UseCLIContextRule
     */
    protected function getRule(): Rule
    {
        return new UseCLIContextRule();
    }
}
