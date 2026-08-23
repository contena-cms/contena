<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(ConfigurationService::class)]
class ConfigurationServiceTest extends TestCase
{
    public function testInvalidDomainIsRejected(): void
    {
        $service = $this->createService([]);

        static::assertFalse($service->checkConfiguration('invalid!', Context::createDefaultContext()));

        $this->expectExceptionObject(SystemConfigException::invalidDomain());
        $service->getConfiguration('invalid!', Context::createDefaultContext());
    }

    public function testMissingPluginConfigurationIsRejected(): void
    {
        $this->expectExceptionObject(SystemConfigException::configurationNotFound('MissingPlugin'));

        $this->createService([])->getConfiguration('MissingPlugin', Context::createDefaultContext());
    }

    public function testPluginConfigurationIsConvertedToAdministrationSchema(): void
    {
        $service = $this->createService([
            [
                'title' => ['en-GB' => 'Settings'],
                'elements' => [
                    [
                        'name' => 'enabled',
                        'type' => 'bool',
                        'label' => ['en-GB' => 'Enabled'],
                        'cacheRelevant' => true,
                    ],
                ],
            ],
        ]);

        $configuration = $service->getConfiguration('ExamplePlugin', Context::createDefaultContext());

        static::assertSame('ExamplePlugin.enabled', $configuration[0]['elements'][0]['name']);
        static::assertSame('bool', $configuration[0]['elements'][0]['type']);
        static::assertTrue($configuration[0]['elements'][0]['config']['cacheRelevant']);
    }

    public function testInactiveFeatureElementsAreRemovedAndListsReindexed(): void
    {
        Feature::registerFeature('SYSTEM_CONFIG_TEST_FLAG');
        $_SERVER['SYSTEM_CONFIG_TEST_FLAG'] = '0';

        $service = $this->createService([
            [
                'title' => ['en-GB' => 'Settings'],
                'elements' => [
                    ['name' => 'hidden', 'type' => 'text', 'flag' => 'SYSTEM_CONFIG_TEST_FLAG'],
                    ['name' => 'visible', 'type' => 'text'],
                ],
            ],
        ]);

        $configuration = $service->getConfiguration('ExamplePlugin', Context::createDefaultContext());

        static::assertIsList($configuration[0]['elements']);
        static::assertSame('ExamplePlugin.visible', $configuration[0]['elements'][0]['name']);
    }

    public function testResolvedConfigurationUsesGlobalValue(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn([
            [
                'title' => ['en-GB' => 'Settings'],
                'elements' => [['name' => 'enabled', 'type' => 'bool', 'defaultValue' => false]],
            ],
        ]);

        $systemConfig = new StaticSystemConfigService([
            'ExamplePlugin.enabled' => true,
        ]);
        $service = new ConfigurationService(
            [new ExamplePlugin(true, '')],
            $configReader,
            $systemConfig,
            new NullLogger()
        );

        $configuration = $service->getResolvedConfiguration(
            'ExamplePlugin',
            Context::createDefaultContext()
        );

        static::assertTrue($configuration[0]['elements'][0]['value']);
    }

    public function testCheckConfigurationReturnsFalseForInvalidXml(): void
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willThrowException(
            UtilException::xmlParsingException('/config.xml', 'Invalid XML')
        );

        $service = new ConfigurationService(
            [new ExamplePlugin(true, '')],
            $configReader,
            new StaticSystemConfigService(),
            new NullLogger()
        );

        static::assertFalse($service->checkConfiguration('ExamplePlugin', Context::createDefaultContext()));
    }

    /**
     * @param array<mixed> $configuration
     */
    private function createService(array $configuration): ConfigurationService
    {
        $configReader = static::createStub(ConfigReader::class);
        $configReader->method('getConfigFromBundle')->willReturn($configuration);

        return new ConfigurationService(
            $configuration === [] ? [] : [new ExamplePlugin(true, '')],
            $configReader,
            new StaticSystemConfigService(),
            new NullLogger()
        );
    }
}

/**
 * @internal
 */
class ExamplePlugin extends Plugin
{
}
