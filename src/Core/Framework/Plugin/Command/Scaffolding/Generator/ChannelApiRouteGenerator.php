<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
class ChannelApiRouteGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const string OPTION_NAME = 'create-channel-api-route';
    private const string OPTION_DESCRIPTION = 'Create an example Channel API route';
    private const string CLI_QUESTION = 'Do you want to create an example Channel API route?';

    private string $servicesPhpEntry = <<<'EOL'

    $services->set(\{{ namespace }}\Core\Content\Example\Channel\ExampleRoute::class)
        ->public()
        ->args([
            service('blog.repository'),
        ]);

EOL;

    private string $routesPhpEntry = <<<'EOL'

    $routes->import('../../Core/**/*Route.php', 'attribute');

EOL;

    public function addScaffoldConfig(
        PluginScaffoldConfiguration $config,
        InputInterface $input,
        SymfonyStyle $io
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);

            return;
        }

        if ($this->shouldAskCliQuestion && $io->confirm(self::CLI_QUESTION)) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);
        }
    }

    public function generateStubs(
        PluginScaffoldConfiguration $configuration,
        StubCollection $stubCollection
    ): void {
        if (!$configuration->hasOption(self::OPTION_NAME) || !$configuration->getOption(self::OPTION_NAME)) {
            return;
        }

        $stubCollection->add($this->createAbstractChannelApiRoute($configuration));
        $stubCollection->add($this->createChannelApiRoute($configuration));
        $stubCollection->add($this->createChannelApiRouteResponse($configuration));

        $stubCollection->append(
            'src/Resources/config/services.php',
            str_replace('{{ namespace }}', $configuration->namespace, $this->servicesPhpEntry)
        );

        $stubCollection->append('src/Resources/config/routes.php', $this->routesPhpEntry);
    }

    private function createAbstractChannelApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/AbstractExampleRoute.php',
            self::STUB_DIRECTORY . '/channel-api-abstract-route.stub',
            ['namespace' => $configuration->namespace]
        );
    }

    private function createChannelApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/ExampleRoute.php',
            self::STUB_DIRECTORY . '/channel-api-route.stub',
            ['namespace' => $configuration->namespace]
        );
    }

    private function createChannelApiRouteResponse(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/ExampleRouteResponse.php',
            self::STUB_DIRECTORY . '/channel-api-response.stub',
            ['namespace' => $configuration->namespace]
        );
    }
}
