<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\ContenaNamespaceStyleRule;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<ContenaNamespaceStyleRule>
 */
class ContenaNamespaceStyleRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NamespaceStyle/AllCorrect.php'], []);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/NoContena.php'], [
            [
                'Namespace must start with Contena',
                3,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/GlobalCommand.php'], [
            [
                'No global Command directories allowed, put your commands in the right domain directory',
                3,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/GlobalException.php'], [
            [
                'No global Exception directories allowed, put your exceptions in the right domain directory',
                3,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new ContenaNamespaceStyleRule();
    }
}
