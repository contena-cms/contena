<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Twig\Extension\ConfigExtension;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Twig\TwigFunction;

/**
 * @internal
 */
#[CoversClass(ConfigExtension::class)]
class ConfigExtensionTest extends TestCase
{
    public function testGetFunctionsReturnsExpectedFunctions(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));
        $functions = $extension->getFunctions();

        static::assertCount(4, $functions);

        $names = array_map(static fn (TwigFunction $f) => $f->getName(), $functions);
        static::assertContains('theme_config', $names);
        static::assertContains('theme_scripts', $names);
        static::assertContains('import_map', $names);
        static::assertContains('theme_css_vars', $names);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedConfigExtractsChannelIdFromContext(): void
    {
        $channelContext = Generator::generateChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', $channelContext->getChannelId())
            ->willReturn('Contena');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['context' => $channelContext], 'core.basicInformation.shopName');

        static::assertSame('Contena', $result);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedConfigExtractsChannelIdFromChannelEntity(): void
    {
        $channel = new ChannelEntity();
        $channel->setId('sales-channel-id');
        $channel->setUniqueIdentifier('sales-channel-id');

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', 'sales-channel-id')
            ->willReturn('Contena');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['channel' => $channel], 'core.basicInformation.shopName');

        static::assertSame('Contena', $result);
    }

    public function testThemeExtractsContextAndThemeId(): void
    {
        $channelContext = Generator::generateChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('theme')
            ->with('color', $channelContext, 'theme-id-xyz')
            ->willReturn('#abc');

        $extension = new ConfigExtension($accessor);
        $result = $extension->theme(
            ['context' => $channelContext, 'themeId' => 'theme-id-xyz'],
            'color'
        );

        static::assertSame('#abc', $result);
    }

    public function testThemeConfigUsesChannelContextFallback(): void
    {
        $themeId = Uuid::randomHex();
        $channelContext = Generator::generateChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('theme')
            ->with('sw-logo-desktop', $channelContext, $themeId)
            ->willReturn('logo.png');

        $extension = new ConfigExtension($config);

        static::assertSame('logo.png', $extension->theme([
            'context' => Context::createDefaultContext(),
            'channelContext' => $channelContext,
            'themeId' => $themeId,
        ], 'sw-logo-desktop'));
    }

    public function testThemeThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(FrontendFrameworkException::channelContextObjectNotFound());

        $extension->theme([], 'color');
    }

    public function testThemeThrowsWhenContextIsNotChannelContext(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(FrontendFrameworkException::channelContextObjectNotFound());

        $extension->theme(['context' => 'not-a-context-object'], 'color');
    }

    public function testScriptsDelegatesToAccessor(): void
    {
        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('scripts')
            ->willReturn(['js/app.js']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(['js/app.js'], $extension->scripts());
    }

    public function testImportMapDelegatesToAccessor(): void
    {
        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('importMap')
            ->willReturn(['Sw:Button' => 'http://localhost/js/components/Sw/Button.js']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(
            ['Sw:Button' => 'http://localhost/js/components/Sw/Button.js'],
            $extension->importMap()
        );
    }

    public function testThemeCssVarsDelegatesToAccessor(): void
    {
        $channelContext = Generator::generateChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('themeCssVars')
            ->with($channelContext, 'theme-id-xyz')
            ->willReturn(['sw-color-brand-primary' => '#0042a0']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(
            ['sw-color-brand-primary' => '#0042a0'],
            $extension->themeCssVars(['context' => $channelContext, 'themeId' => 'theme-id-xyz'])
        );
    }

    public function testThemeCssVarsThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(FrontendFrameworkException::channelContextObjectNotFound());

        $extension->themeCssVars([]);
    }
}
