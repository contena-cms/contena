<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\SystemConfig\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\UtilException;
use Contena\Core\System\System;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\BrokenConfigPlugin\BrokenConfigPlugin;
use Contena\Tests\Integration\Core\System\SystemConfig\Service\_fixtures\ValidConfigPlugin\ValidConfigPlugin;

/**
 * @internal
 */
class ConfigurationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCheckConfigurationReturnsFalseForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // Should return false instead of throwing UtilXmlParsingException
        static::assertFalse(
            $configurationService->checkConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testCheckConfigurationReturnsTrueForValidConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new ValidConfigPlugin(true, __DIR__ . '/_fixtures/ValidConfigPlugin'),
        ]);

        static::assertTrue(
            $configurationService->checkConfiguration('ValidConfigPlugin.config', Context::createDefaultContext())
        );
    }

    public function testGetConfigurationThrowsExceptionForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getConfiguration should still throw the exception (only checkConfiguration catches it)
        $this->expectException(UtilException::class);
        $configurationService->getConfiguration('BrokenConfigPlugin.config', Context::createDefaultContext());
    }

    public function testGetResolvedConfigurationReturnsEmptyArrayForBrokenConfigXml(): void
    {
        $configurationService = $this->createConfigurationService([
            new BrokenConfigPlugin(true, __DIR__ . '/_fixtures/BrokenConfigPlugin'),
        ]);

        // getResolvedConfiguration uses checkConfiguration, so it should return empty array
        $result = $configurationService->getResolvedConfiguration(
            'BrokenConfigPlugin.config',
            Context::createDefaultContext()
        );

        static::assertSame([], $result);
    }

    public function testBasicInformationContainsFrontendConfiguration(): void
    {
        $configuration = $this->createConfigurationService([])->getConfiguration(
            'core.basicInformation',
            Context::createDefaultContext()
        );

        $fieldNames = [];
        foreach ($configuration as $card) {
            foreach ($card['elements'] ?? [] as $element) {
                $fieldNames[] = $element['name'];
            }
        }

        static::assertSame([
            'core.basicInformation.siteName',
            'core.basicInformation.email',
            'core.basicInformation.metaAuthor',
            'core.basicInformation.familyFriendly',
            'core.basicInformation.tosPage',
            'core.basicInformation.privacyPage',
            'core.basicInformation.imprintPage',
            'core.basicInformation.http404Page',
            'core.basicInformation.maintenancePage',
            'core.basicInformation.useDefaultCookieConsent',
            'core.basicInformation.acceptAllCookies',
            'core.basicInformation.robotsDisableDefaults',
            'core.basicInformation.robotsRules',
            'core.basicInformation.activeCaptchasV2',
            'core.basicInformation.metaRobots',
        ], $fieldNames);
    }

    /**
     * @param list<Plugin> $plugins
     */
    private function createConfigurationService(array $plugins): ConfigurationService
    {
        return new ConfigurationService(
            [
                new System(),
                ...$plugins,
            ],
            new ConfigReader(),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }
}
