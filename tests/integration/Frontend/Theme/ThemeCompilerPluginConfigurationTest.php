<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\Exception\InvalidPlatformVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin;
use Contena\Core\Kernel;
use Contena\Core\System\SystemConfig\Service\AppConfigReader;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\CompilerConfiguration;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ThemeCompilerPluginConfigurationTest extends TestCase
{
    use KernelTestBehaviour;

    // ===================================
    // Plugin Configuration Integration Tests
    // ===================================

    public function testCompilesWithPluginScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            '$simple-plugin-backgroundcolor: #ffffff; $simple-plugin-fontcolor: #000000; ' . $testScss
        );

        static::assertStringContainsString('.test-selector-plugin', $result);
        static::assertStringContainsString('#ffffff', $result);
        static::assertStringContainsString('#000000', $result);
    }

    public function testCompilesWithAppScssVariables(): void
    {
        $testScss = <<<'SCSS'
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            '$no-theme-custom-css-backgroundcolor: #aabbcc; $no-theme-custom-css-fontcolor: #ddeeff; ' . $testScss
        );

        static::assertStringContainsString('.test-selector-app', $result);
        static::assertStringContainsString('#aabbcc', $result);
        static::assertStringContainsString('#ddeeff', $result);
    }

    public function testCompilesPluginAndAppCssWithNullValueHandling(): void
    {
        $testScss = <<<'SCSS'
.test-selector-plugin {
    background: $simple-plugin-backgroundcolor;
    color: $simple-plugin-fontcolor;
    border: $simple-plugin-bordercolor;
}
.test-selector-app {
    background: $no-theme-custom-css-backgroundcolor;
    color: $no-theme-custom-css-fontcolor;
    border: $no-theme-custom-css-bordercolor;
}
SCSS;

        // Build variables with nulls — border variables intentionally null
        $variables = '$simple-plugin-backgroundcolor: #fff; ';
        $variables .= '$simple-plugin-fontcolor: #eee; ';
        $variables .= '$simple-plugin-bordercolor: null; ';
        $variables .= '$no-theme-custom-css-backgroundcolor: #aaa; ';
        $variables .= '$no-theme-custom-css-fontcolor: #eee; ';
        $variables .= '$no-theme-custom-css-bordercolor: null; ';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $variables . $testScss
        );

        static::assertStringContainsString('.test-selector-plugin', $result);
        static::assertStringContainsString('background:#fff', str_replace(' ', '', $result));
        static::assertStringContainsString('color:#eee', str_replace(' ', '', $result));

        static::assertStringContainsString('.test-selector-app', $result);
        static::assertStringContainsString('background:#aaa', str_replace(' ', '', $result));

        $normalizedResult = str_replace([' ', "\n", "\r"], '', strtolower($result));
        static::assertStringNotContainsString(
            'border:',
            $normalizedResult,
            'Border properties should be omitted when variable value is null'
        );
    }

    // ===================================
    // Database Resilience Tests
    // ===================================

    public function testHandlesDatabaseException(): void
    {
        $configurationService = $this->getConfigurationServiceDbException([
        ]);

        $frontendPluginRegistry = $this->getFrontendPluginRegistry([
        ]);

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            [],
            TestDefaults::CHANNEL,
            Context::createDefaultContext()
        );

        $subscriber = new ThemeCompilerEnrichScssVarSubscriber($configurationService, $frontendPluginRegistry);

        $subscriber->enrichExtensionVars($event);

        static::assertCount(0, $event->getVariables());
    }

    // ===================================
    // Helper Methods
    // ===================================

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getConfigurationServiceDbException(array $plugins): ConfigurationService
    {
        return new ThemeCompilerPluginConfigurationServiceException(
            $plugins,
            new ConfigReader(),
            static::getContainer()->get(AppConfigReader::class),
            static::getContainer()->get('app.repository'),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get(LoggerInterface::class)
        );
    }

    /**
     * @param array<int, Plugin> $plugins
     */
    private function getFrontendPluginRegistry(array $plugins): FrontendPluginRegistry
    {
        $kernel = static::createStub(Kernel::class);
        $kernel
            ->method('getBundles')
            ->willReturn($plugins);

        return new FrontendPluginRegistry(
            $kernel,
            static::getContainer()->get(FrontendPluginConfigurationFactory::class),
            static::getContainer()->get(ActiveAppsLoader::class)
        );
    }
}

/**
 * @internal
 */
class ThemeCompilerPluginConfigurationServiceException extends ConfigurationService
{
    /**
     * @throws Exception
     */
    public function checkConfiguration(string $domain, Context $context): bool
    {
        throw new InvalidPlatformVersion('any');
    }
}
