<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Command\Scaffolding\Generator;

use Contena\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Stub;
use Contena\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\AddScaffoldConfigDefaultBehaviour;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\HasCommandOption;

/**
 * @internal
 */
class ChannelApiRouteGenerator implements ScaffoldingGenerator
{
    use AddScaffoldConfigDefaultBehaviour;
    use HasCommandOption;

    public const OPTION_NAME = 'create-channel-api-route';
    private const OPTION_TITLE = 'Store API Route';
    private const OPTION_DESCRIPTION = 'Create an example channel-api route';
    private const OPTION_DESCRIPTION_LONG = 'A custom Store API route is an endpoint that exposes plugin-specific functionality or data through Contena\'s Store API. Use one when a headless frontend or external client needs to access custom shop functionality via structured API requests.';
    private const CLI_QUESTION = 'Do you want to create an example channel-api route?';

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
        OutputInterface $output
    ): void {
        $hasOption = $input->getOption(self::OPTION_NAME);

        if ($hasOption) {
            $config->addOption(self::OPTION_NAME, true);
            $config->addOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME, true);

            return;
        }

        if ($this->askCliQuestion($input, $output, self::CLI_QUESTION)) {
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
            str_replace(
                '{{ namespace }}',
                $configuration->namespace,
                $this->servicesPhpEntry
            )
        );

        $stubCollection->append(
            'src/Resources/config/routes.php',
            $this->routesPhpEntry
        );
    }

    private function createAbstractChannelApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/AbstractExampleRoute.php',
            self::STUB_DIRECTORY . '/channel-api-abstract-route.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }

    private function createChannelApiRoute(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/ExampleRoute.php',
            self::STUB_DIRECTORY . '/channel-api-route.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }

    private function createChannelApiRouteResponse(PluginScaffoldConfiguration $configuration): Stub
    {
        return Stub::template(
            'src/Core/Content/Example/Channel/ExampleRouteResponse.php',
            self::STUB_DIRECTORY . '/channel-api-response.stub',
            [
                'namespace' => $configuration->namespace,
            ]
        );
    }
}
