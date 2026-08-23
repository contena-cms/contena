<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ChannelApiRouteGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(ChannelApiRouteGenerator::class)]
class ChannelApiRouteGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new ChannelApiRouteGenerator();

        static::assertTrue($generator->hasCommandOption());
        static::assertNotEmpty($generator->getCommandOptionName());
        static::assertNotEmpty($generator->getCommandOptionDescription());
    }

    #[DataProvider('addScaffoldConfigProvider')]
    public function testAddScaffoldConfig(bool $option, bool $confirm, bool $expected): void
    {
        $configuration = self::getConfig();
        $input = static::createStub(InputInterface::class);
        $input->method('getOption')->willReturn($option);
        $io = static::createStub(SymfonyStyle::class);
        $io->method('confirm')->willReturn($confirm);

        new ChannelApiRouteGenerator()->addScaffoldConfig($configuration, $input, $io);

        static::assertSame($expected, $configuration->hasOption(ChannelApiRouteGenerator::OPTION_NAME));
        if ($expected) {
            static::assertTrue($configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME));
        }
    }

    public static function addScaffoldConfigProvider(): \Generator
    {
        yield 'explicit option' => [true, false, true];
        yield 'confirmed prompt' => [false, true, true];
        yield 'declined prompt' => [false, false, false];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(PluginScaffoldConfiguration $config, array $expected): void
    {
        $stubs = new StubCollection();
        new ChannelApiRouteGenerator()->generateStubs($config, $stubs);

        static::assertCount(\count($expected), $stubs);
        foreach ($expected as $stub) {
            static::assertTrue($stubs->has($stub));
        }
    }

    public static function generateProvider(): \Generator
    {
        yield 'without option' => [self::getConfig(), []];
        yield 'disabled option' => [self::getConfig([ChannelApiRouteGenerator::OPTION_NAME => false]), []];
        yield 'enabled option' => [self::getConfig([ChannelApiRouteGenerator::OPTION_NAME => true]), [
            'src/Resources/config/services.php',
            'src/Resources/config/routes.php',
            'src/Core/Content/Example/Channel/AbstractExampleRoute.php',
            'src/Core/Content/Example/Channel/ExampleRoute.php',
            'src/Core/Content/Example/Channel/ExampleRouteResponse.php',
        ]];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function getConfig(array $options = []): PluginScaffoldConfiguration
    {
        return new PluginScaffoldConfiguration('TestPlugin', 'MyNamespace', '/path/to/directory', $options);
    }
}
