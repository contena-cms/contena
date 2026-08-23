<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Frontend;
use Contena\Frontend\Test\Theme\ThemeRuntimeConfigTestService;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\Twig\ThemeInheritanceBuilder;
use Contena\Tests\Integration\Frontend\Theme\fixtures\ConfigWithoutFrontendDefined\ConfigWithoutFrontendDefined;
use Contena\Tests\Integration\Frontend\Theme\fixtures\InheritanceWithConfig\InheritanceWithConfig;
use Contena\Tests\Integration\Frontend\Theme\fixtures\PluginWildcardAndExplicit\PluginWildcardAndExplicit;
use Contena\Tests\Integration\Frontend\Theme\fixtures\SimplePlugin\SimplePlugin;
use Contena\Tests\Integration\Frontend\Theme\fixtures\SimpleTheme\SimpleTheme;
use Contena\Tests\Integration\Frontend\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Contena\Tests\Integration\Frontend\Theme\fixtures\ThemeWithoutFrontend\ThemeWithoutFrontend;

/**
 * @internal
 */
class ThemeInheritanceBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractFrontendPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->configFactory = static::getContainer()->get(FrontendPluginConfigurationFactory::class);
    }

    public function testInheritanceWithConfig(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['InheritanceWithConfig' => 1, 'Frontend' => 1],
            ['InheritanceWithConfig' => true, 'Frontend' => true]
        );

        static::assertSame(['InheritanceWithConfig', 'Frontend'], array_keys($inheritance));
    }

    public function testEnsurePlugins(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new InheritanceWithConfig()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['InheritanceWithConfig' => 1, 'Frontend' => 1, 'PayPal' => 1],
            ['InheritanceWithConfig' => true, 'Frontend' => true]
        );

        static::assertSame(['PayPal', 'InheritanceWithConfig', 'Frontend'], array_keys($inheritance));
    }

    public function testConfigWithoutFrontendDefined(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new ConfigWithoutFrontendDefined()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ConfigWithoutFrontendDefined' => 1, 'Frontend' => 1, 'PayPal' => 1],
            ['ConfigWithoutFrontendDefined' => true]
        );

        static::assertSame(['PayPal', 'ConfigWithoutFrontendDefined'], array_keys($inheritance));
    }

    public function testPluginWildcardAndExplicit(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new PluginWildcardAndExplicit()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['PluginWildcardAndExplicit' => 1, 'Frontend' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['PluginWildcardAndExplicit' => true, 'Frontend' => true]
        );

        static::assertSame(['CustomProducts', 'PluginWildcardAndExplicit', 'PayPal', 'Frontend'], array_keys($inheritance));
    }

    public function testThemeWithoutFrontend(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new ThemeWithoutFrontend()),
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('CustomProducts', SimplePlugin::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ThemeWithoutFrontend' => 1, 'Frontend' => 1, 'PayPal' => 1, 'CustomProducts' => 1],
            ['ThemeWithoutFrontend' => true, 'Frontend' => true]
        );

        static::assertSame(['CustomProducts', 'ThemeWithoutFrontend', 'PayPal'], array_keys($inheritance));
    }

    public function testMultiInheritance(): void
    {
        $configs = new FrontendPluginConfigurationCollection([
            $this->configFactory->createFromBundle(new Frontend()),
            $this->configFactory->createFromBundle(new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin')),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeA', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeB', SimpleTheme::class)),
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeC', SimpleTheme::class)),

            // paypal is a plugin and should be registered
            $this->configFactory->createFromBundle($this->getMockedPlugin('PayPal', SimplePlugin::class)),

            // theme d is not included in theme.json
            $this->configFactory->createFromBundle($this->getMockedPlugin('ThemeD', SimpleTheme::class)),
        ]);

        $inheritance = $this->createInheritanceBuilder($configs)->build(
            ['ThemeWithMultiInheritance' => 1, 'ThemeA' => 1, 'ThemeB' => 1, 'ThemeC' => 1, 'ThemeD' => 1, 'PayPal' => 1],
            ['ThemeWithMultiInheritance' => true]
        );

        static::assertSame(
            ['ThemeWithMultiInheritance', 'ThemeC', 'PayPal', 'ThemeB', 'ThemeA'],
            array_keys($inheritance)
        );
    }

    /**
     * @param class-string $pluginClass
     */
    private function getMockedPlugin(string $pluginName, string $pluginClass): Bundle
    {
        /** @var Bundle $bundle */
        $bundle = new $pluginClass(true, __DIR__ . '/fixtures/SimplePlugin');

        $reflection = new \ReflectionClass($pluginClass);
        $reflection->getProperty('name')->setValue($bundle, $pluginName);

        return $bundle;
    }

    private function createInheritanceBuilder(FrontendPluginConfigurationCollection $configurationCollection): ThemeInheritanceBuilder
    {
        $themeRuntimeConfigService = new ThemeRuntimeConfigTestService($configurationCollection);

        return new ThemeInheritanceBuilder($themeRuntimeConfigService);
    }
}
