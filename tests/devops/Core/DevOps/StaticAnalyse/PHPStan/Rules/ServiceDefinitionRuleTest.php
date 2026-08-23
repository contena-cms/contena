<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Symfony\XmlServiceMapFactory;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\ServiceDefinitionCollector;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\ServiceDefinitionRule;

/**
 * @internal
 *
 * @extends RuleTestCase<ServiceDefinitionRule>
 */
class ServiceDefinitionRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';
        $files = [
            $fixtureDir . '/src/Core/Framework/Example/CoreContract.php',
            $fixtureDir . '/src/Core/Framework/Example/CoreServiceInCore.php',
            $fixtureDir . '/src/Core/Framework/Example/PhpCoreService.php',
            $fixtureDir . '/src/Core/Framework/Example/XmlCoreService.php',
        ];

        $this->analyse($files, [
            [
                'src/Frontend/DependencyInjection/services.php - service "Contena\Core\Framework\Example\PhpCoreService" is registered in Frontend but its effective class "Contena\Core\Framework\Example\PhpCoreService" belongs to Core. Register it in a Core DependencyInjection file instead.',
                1,
            ],
            [
                'src/Frontend/DependencyInjection/services.xml - service "Contena\Core\Framework\Example\XmlCoreService" is registered in Frontend but its effective class "Contena\Core\Framework\Example\XmlCoreService" belongs to Core. Register it in a Core DependencyInjection file instead.',
                1,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        return new ServiceDefinitionRule($factory->create(), $fixtureDir);
    }

    /**
     * @return list<ServiceDefinitionCollector>
     */
    protected function getCollectors(): array
    {
        $fixtureDir = __DIR__ . '/data/ServiceDefinitionRule';

        /** @phpstan-ignore phpstanApi.constructor */
        $factory = new XmlServiceMapFactory($fixtureDir . '/container.xml');

        /** @phpstan-ignore phpstanApi.method */
        $serviceMap = $factory->create();

        return [
            new ServiceDefinitionCollector($serviceMap),
        ];
    }
}
