<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Storefront\Framework\StorefrontFrameworkException;
use Contena\Storefront\Framework\Twig\Extension\ConfigExtension;
use Contena\Storefront\Framework\Twig\TemplateConfigAccessor;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('discovery')]
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
    public function testDeprecatedConfigExtractsSalesChannelIdFromContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', $salesChannelContext->getSalesChannelId())
            ->willReturn('Contena');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['context' => $salesChannelContext], 'core.basicInformation.shopName');

        static::assertSame('Contena', $result);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedConfigExtractsSalesChannelIdFromSalesChannelEntity(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('sales-channel-id');
        $salesChannel->setUniqueIdentifier('sales-channel-id');

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor
            ->expects($this->once())
            ->method('config')
            ->with('core.basicInformation.shopName', 'sales-channel-id')
            ->willReturn('Contena');

        $extension = new ConfigExtension($accessor);
        $result = $extension->config(['salesChannel' => $salesChannel], 'core.basicInformation.shopName');

        static::assertSame('Contena', $result);
    }

    public function testThemeExtractsContextAndThemeId(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('theme')
            ->with('color', $salesChannelContext, 'theme-id-xyz')
            ->willReturn('#abc');

        $extension = new ConfigExtension($accessor);
        $result = $extension->theme(
            ['context' => $salesChannelContext, 'themeId' => 'theme-id-xyz'],
            'color'
        );

        static::assertSame('#abc', $result);
    }

    public function testThemeConfigUsesSalesChannelContextFallback(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $config = $this->createMock(TemplateConfigAccessor::class);
        $config
            ->expects($this->once())
            ->method('theme')
            ->with('sw-logo-desktop', $salesChannelContext, $themeId)
            ->willReturn('logo.png');

        $extension = new ConfigExtension($config);

        static::assertSame('logo.png', $extension->theme([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
            'themeId' => $themeId,
        ], 'sw-logo-desktop'));
    }

    public function testThemeThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

        $extension->theme([], 'color');
    }

    public function testThemeThrowsWhenContextIsNotSalesChannelContext(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

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
        $salesChannelContext = Generator::generateSalesChannelContext();

        $accessor = $this->createMock(TemplateConfigAccessor::class);
        $accessor->expects($this->once())
            ->method('themeCssVars')
            ->with($salesChannelContext, 'theme-id-xyz')
            ->willReturn(['sw-color-brand-primary' => '#0042a0']);

        $extension = new ConfigExtension($accessor);

        static::assertSame(
            ['sw-color-brand-primary' => '#0042a0'],
            $extension->themeCssVars(['context' => $salesChannelContext, 'themeId' => 'theme-id-xyz'])
        );
    }

    public function testThemeCssVarsThrowsWhenContextKeyIsMissing(): void
    {
        $extension = new ConfigExtension(static::createStub(TemplateConfigAccessor::class));

        $this->expectExceptionObject(StorefrontFrameworkException::salesChannelContextObjectNotFound());

        $extension->themeCssVars([]);
    }
}
