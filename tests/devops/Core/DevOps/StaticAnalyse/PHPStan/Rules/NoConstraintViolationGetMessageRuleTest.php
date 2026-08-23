<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoConstraintViolationGetMessageRule;

/**
 * @internal
 *
 * @extends RuleTestCase<NoConstraintViolationGetMessageRule>
 */
class NoConstraintViolationGetMessageRuleTest extends RuleTestCase
{
    private const string ERROR = 'Do not use ConstraintViolationInterface::getMessage(). Use getCode() and translate it through the Contena translator.';

    public function testGetMessageIsForbiddenAndGetCodeIsAllowed(): void
    {
        $this->analyse([__DIR__ . '/data/NoConstraintViolationGetMessageRule/Usage.php'], [
            [self::ERROR, 13],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoConstraintViolationGetMessageRule();
    }
}
