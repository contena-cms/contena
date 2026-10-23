<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal\InternalMethodRule;

/**
 * @internal
 *
 * @extends RuleTestCase<InternalMethodRule>
 */
class InternalMethodRuleTest extends RuleTestCase
{
    public function testInternalServiceConstructorCannotBeDeprecated(): void
    {
        $fixtureDir = __DIR__ . '/data/InternalMethodRule';

        $this->analyse([$fixtureDir . '/InternalService.php'], [
            [
                'A deprecation annotation must not be used on internal constructors of DI services. Put it on the affected constructor parameter instead.',
                12,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/InternalMethodRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new InternalMethodRule($factory->create());
    }
}
